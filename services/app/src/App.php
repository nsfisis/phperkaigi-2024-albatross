<?php

declare(strict_types=1);

namespace Nsfisis\Albatross;

use DI\Bridge\Slim\Bridge as AppFactory;
use DI\Container;
use Middlewares\PhpSession;
use Nsfisis\Albatross\Auth\AuthProviderInterface;
use Nsfisis\Albatross\Auth\ForteeAuth;
use Nsfisis\Albatross\Database\Connection;
use Nsfisis\Albatross\Form\FormBase;
use Nsfisis\Albatross\Form\FormState;
use Nsfisis\Albatross\Form\FormSubmissionFailureException;
use Nsfisis\Albatross\Forms\AdminQuizEditForm;
use Nsfisis\Albatross\Forms\AdminQuizNewForm;
use Nsfisis\Albatross\Forms\AdminTestcaseEditForm;
use Nsfisis\Albatross\Forms\AdminTestcaseNewForm;
use Nsfisis\Albatross\Forms\AdminUserEditForm;
use Nsfisis\Albatross\Forms\AnswerNewForm;
use Nsfisis\Albatross\Forms\LoginForm;
use Nsfisis\Albatross\Middlewares\AdminRequiredMiddleware;
use Nsfisis\Albatross\Middlewares\AuthRequiredMiddleware;
use Nsfisis\Albatross\Middlewares\CacheControlPrivateMiddleware;
use Nsfisis\Albatross\Middlewares\CurrentUserMiddleware;
use Nsfisis\Albatross\Middlewares\TrailingSlash;
use Nsfisis\Albatross\Middlewares\TwigMiddleware;
use Nsfisis\Albatross\Models\AggregatedExecutionStatus;
use Nsfisis\Albatross\Models\Answer;
use Nsfisis\Albatross\Models\Quiz;
use Nsfisis\Albatross\Models\User;
use Nsfisis\Albatross\Repositories\AnswerRepository;
use Nsfisis\Albatross\Repositories\QuizRepository;
use Nsfisis\Albatross\Repositories\TestcaseExecutionRepository;
use Nsfisis\Albatross\Repositories\TestcaseRepository;
use Nsfisis\Albatross\Repositories\UserRepository;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App as SlimApp;
use Slim\Csrf\Guard as CsrfGuard;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\RouteParserInterface;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;

final class App
{
    private SlimApp $app;

    public function __construct(
        private readonly Config $config,
    ) {
        $container = new Container();
        $container->set(AuthProviderInterface::class, new ForteeAuth(apiEndpoint: $config->forteeApiEndpoint));
        $container->set(Connection::class, function () use ($config) {
            return new Connection(
                driver: 'pgsql',
                host: $config->dbHost,
                port: $config->dbPort,
                name: $config->dbName,
                user: $config->dbUser,
                password: $config->dbPassword,
            );
        });
        $app = AppFactory::create($container);
        $container->set(ResponseFactoryInterface::class, $app->getResponseFactory());
        $container->set(RouteParserInterface::class, $app->getRouteCollector()->getRouteParser());

        $app->addRoutingMiddleware();

        $app->setBasePath($this->config->basePath);

        $app->get('/', $this->handleQuizList(...))->setName('quiz_list');
        $app->get('/q/', $this->handleRedirectQuizList(...))->setName('redirect_quiz_list');

        $app->get('/login/', $this->handleLogin(...))->setName('login');
        $app->post('/login/', $this->handleLoginPost(...))->setName('login_post');

        $app->group('/q', function (RouteCollectorProxy $group) use ($app) {
            $group->group('/{qslug}', function (RouteCollectorProxy $group) use ($app) {
                $group->get('/', $this->handleQuizView(...))->setName('quiz_view');

                $group->group('/a', function (RouteCollectorProxy $group) use ($app) {
                    $group->get('/', $this->handleAnswerList(...))->setName('answer_list');
                    $group->get('/new/', $this->handleAnswerNew(...))
                        ->add(AuthRequiredMiddleware::create($app, 'login'))
                        ->setName('answer_new');
                    $group->post('/new/', $this->handleAnswerNewPost(...))
                        ->add(AuthRequiredMiddleware::create($app, 'login'))
                        ->setName('answer_new_post');
                    $group->get('/{anum:[1-9][0-9]*}/', $this->handleAnswerView(...))->setName('answer_view');
                });
            });
        });

        $app->group('/admin', function (RouteCollectorProxy $group) {
            $group->get('/', $this->handleAdminOverview(...))->setName('admin_overview');

            $group->group('/u', function (RouteCollectorProxy $group) {
                $group->get('/', $this->handleAdminUserList(...))->setName('admin_user_list');

                $group->get('/{username}/', $this->handleAdminUserEdit(...))->setName('admin_user_edit');
                $group->post('/{username}/', $this->handleAdminUserEditPost(...))->setName('admin_user_edit_post');
            });

            $group->group('/q', function (RouteCollectorProxy $group) {
                $group->get('/', $this->handleAdminQuizList(...))->setName('admin_quiz_list');

                $group->get('/new/', $this->handleAdminQuizNew(...))->setName('admin_quiz_new');
                $group->post('/new/', $this->handleAdminQuizNewPost(...))->setName('admin_quiz_new_post');

                $group->group('/{qslug}', function (RouteCollectorProxy $group) {
                    $group->get('/', $this->handleAdminQuizEdit(...))->setName('admin_quiz_edit');
                    $group->post('/', $this->handleAdminQuizEditPost(...))->setName('admin_quiz_edit_post');

                    $group->group('/a', function (RouteCollectorProxy $group) {
                        $group->get('/', $this->handleAdminAnswerList(...))->setName('admin_answer_list');
                        $group->post('/rerun/', $this->handleAdminAnswerRerunAllAnswersPost(...))->setName('admin_answer_rerun_all_answers_post');

                        $group->group('/{anum:[1-9][0-9]*}', function (RouteCollectorProxy $group) {
                            $group->get('/', $this->handleAdminAnswerEdit(...))->setName('admin_answer_edit');
                            $group->post('/rerun/all/', $this->handleAdminAnswerRerunAllTestcasesPost(...))->setName('admin_answer_rerun_all_testcases_post');
                            $group->post('/rerun/{txid:[1-9][0-9]*}/', $this->handleAdminAnswerRerunSingleTestcasePost(...))->setName('admin_answer_rerun_single_testcase_post');
                        });
                    });

                    $group->group('/t', function (RouteCollectorProxy $group) {
                        $group->get('/', $this->handleAdminTestcaseList(...))->setName('admin_testcase_list');

                        $group->get('/new/', $this->handleAdminTestcaseNew(...))->setName('admin_testcase_new');
                        $group->post('/new/', $this->handleAdminTestcaseNewPost(...))->setName('admin_testcase_new_post');

                        $group->group('/{tid:[1-9][0-9]*}', function (RouteCollectorProxy $group) {
                            $group->get('/', $this->handleAdminTestcaseEdit(...))->setName('admin_testcase_edit');
                            $group->post('/', $this->handleAdminTestcaseEditPost(...))->setName('admin_testcase_edit_post');
                            $group->post('/delete/', $this->handleAdminTestcaseDeletePost(...))->setName('admin_testcase_delete_post');
                        });
                    });
                });
            });
        })
        ->add(AdminRequiredMiddleware::create($app))
        ->add(AuthRequiredMiddleware::create($app, 'login'));

        $app->get('/api/answers/{aid:[1-9][0-9]*}/statuses', $this->handleApiAnswerStatuses(...))
            ->add(AuthRequiredMiddleware::create($app, 'login'))
            ->setName('api_answer_statuses');
        $app->get('/api/quizzes/{qid:[1-9][0-9]*}/chart', $this->handleApiQuizChart(...))
            ->setName('api_quiz_chart');

        $app->add(TwigMiddleware::class);
        $app->add(new CacheControlPrivateMiddleware());
        $app->add(CurrentUserMiddleware::class);
        $app->add(CsrfGuard::class);
        $app->add(new PhpSession());
        $app->add((new TrailingSlash(true))->redirect($app->getResponseFactory()));

        $app->addErrorMiddleware(
            displayErrorDetails: $config->displayErrors,
            logErrors: true,
            logErrorDetails: true,
            logger: null, // TODO
        );

        $this->app = $app;
    }

    public function run(): void
    {
        $this->app->run();
    }

    private function handleLogin(
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        UserRepository $userRepo,
        AuthProviderInterface $authProvider,
    ): ResponseInterface {
        if ($request->getAttribute('current_user') !== null) {
            return $this->makeRedirectResponse(
                $response,
                $request->getQueryParams()['to'] ?? $routeParser->urlFor('quiz_list'),
            );
        }

        $form = new LoginForm(
            null,
            destination: $request->getQueryParams()['to'] ?? null,
            routeParser: $routeParser,
            userRepo: $userRepo,
            authProvider: $authProvider,
        );
        return $this->showForm($request, $response, 'login.html.twig', $form);
    }

    private function handleLoginPost(
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        UserRepository $userRepo,
        AuthProviderInterface $authProvider,
    ): ResponseInterface {
        if ($request->getAttribute('current_user') !== null) {
            return $this->makeRedirectResponse(
                $response,
                $request->getQueryParams()['to'] ?? $routeParser->urlFor('quiz_list'),
            );
        }

        $form = new LoginForm(
            FormState::fromRequest($request),
            destination: $request->getQueryParams()['to'] ?? null,
            routeParser: $routeParser,
            userRepo: $userRepo,
            authProvider: $authProvider,
        );
        return $this->submitForm($request, $response, 'login.html.twig', $form);
    }

    private function handleQuizList(
        ServerRequestInterface $request,
        ResponseInterface $response,
        QuizRepository $quizRepo,
    ): ResponseInterface {
        $quizzes = $quizRepo->listAll();

        return $this->render($request, $response, 'quiz_list.html.twig', [
            'page_title' => '問題一覧',
            'quizzes' => $quizzes,
        ]);
    }

    private function handleRedirectQuizList(
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
    ): ResponseInterface {
        return $this->makeRedirectResponse($response, $routeParser->urlFor('quiz_list'), 301);
    }

    private function handleQuizView(
        string $qslug,
        ServerRequestInterface $request,
        ResponseInterface $response,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
    ): ResponseInterface {
        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpNotFoundException($request);
        }
        if ($quiz->isRankingHidden()) {
            $ranking = null;
        } else {
            $ranking = $answerRepo->getRanking($quiz->quiz_id, upto: 20);
        }

        $currentUser = $this->getCurrentUser($request);
        return $this->render($request, $response, 'quiz_view.html.twig', [
            'page_title' => "問題 #{$quiz->quiz_id}",
            'quiz' => $quiz,
            'ranking' => $ranking,
            'is_ranking_hidden' => $quiz->isRankingHidden(),
            'is_open' => $quiz->isOpenToAnswer(),
            'current_user_id' => $currentUser?->user_id,
        ]);
    }

    private function handleAnswerList(
        string $qslug,
        ServerRequestInterface $request,
        ResponseInterface $response,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
    ): ResponseInterface {
        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpNotFoundException($request);
        }
        $currentUser = $this->getCurrentUser($request);
        if ($quiz->isRankingHidden()) {
            if ($currentUser === null) {
                $answers = [];
            } else {
                $answers = $answerRepo->listByQuizIdAndAuthorId($quiz->quiz_id, $currentUser->user_id);
            }
        } else {
            $answers = $answerRepo->listByQuizId($quiz->quiz_id);
        }

        return $this->render($request, $response, 'answer_list.html.twig', [
            'page_title' => "問題 #{$quiz->quiz_id} - 回答一覧",
            'quiz' => $quiz,
            'answers' => $answers,
            'is_ranking_hidden' => $quiz->isRankingHidden(),
            'is_open' => $quiz->isOpenToAnswer(),
            'current_user_id' => $currentUser?->user_id,
        ]);
    }

    private function handleAnswerNew(
        string $qslug,
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
        TestcaseExecutionRepository $testcaseExecutionRepo,
        Connection $conn,
    ): ResponseInterface {
        $currentUser = $this->getCurrentUser($request);
        assert(
            isset($currentUser),
            'The "current_user" attribute should be set because this route has AuthRequiredMiddleware',
        );

        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null || $quiz->isClosedToAnswer()) {
            throw new HttpNotFoundException($request);
        }

        $form = new AnswerNewForm(
            null,
            currentUser: $currentUser,
            quiz: $quiz,
            routeParser: $routeParser,
            answerRepo: $answerRepo,
            testcaseExecutionRepo: $testcaseExecutionRepo,
            conn: $conn,
        );
        return $this->showForm($request, $response, 'answer_new.html.twig', $form);
    }

    private function handleAnswerNewPost(
        string $qslug,
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
        TestcaseExecutionRepository $testcaseExecutionRepo,
        Connection $conn,
    ): ResponseInterface {
        $currentUser = $this->getCurrentUser($request);
        assert(
            isset($currentUser),
            'The "current_user" attribute should be set because this route has AuthRequiredMiddleware',
        );

        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpBadRequestException($request);
        }

        $form = new AnswerNewForm(
            FormState::fromRequest($request),
            currentUser: $currentUser,
            quiz: $quiz,
            routeParser: $routeParser,
            answerRepo: $answerRepo,
            testcaseExecutionRepo: $testcaseExecutionRepo,
            conn: $conn,
        );
        return $this->submitForm($request, $response, 'answer_new.html.twig', $form);
    }

    private function handleAnswerView(
        string $qslug,
        string $anum,
        ServerRequestInterface $request,
        ResponseInterface $response,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
        TestcaseExecutionRepository $testcaseExecutionRepo,
    ): ResponseInterface {
        $anum = (int)$anum;
        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpNotFoundException($request);
        }
        $answer = $answerRepo->findByQuizIdAndAnswerNumber($quiz->quiz_id, answer_number: $anum);
        if ($answer === null) {
            throw new HttpNotFoundException($request);
        }
        $currentUser = $this->getCurrentUser($request);
        if (!$quiz->isFinished() && $answer->author_id !== $currentUser?->user_id) {
            throw new HttpForbiddenException($request);
        }

        if ($currentUser !== null) {
            $phper_token = $this->obtainPhperToken($quiz, $answer, $currentUser);
        }

        return $this->render($request, $response, 'answer_view.html.twig', [
            'page_title' => "問題 #{$quiz->quiz_id} - 回答 #{$answer->answer_number}",
            'quiz' => $quiz,
            'answer' => $answer,
            'testcase_executions' => $testcaseExecutionRepo->listByAnswerId($answer->answer_id),
            'phper_token' => $phper_token ?? null,
        ]);
    }

    private function handleAdminOverview(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        return $this->render($request, $response, 'admin_overview.html.twig', [
            'page_title' => '管理画面',
        ]);
    }

    private function handleAdminUserList(
        ServerRequestInterface $request,
        ResponseInterface $response,
        UserRepository $userRepo,
    ): ResponseInterface {
        $users = $userRepo->listAll();

        return $this->render($request, $response, 'admin_user_list.html.twig', [
            'page_title' => '管理画面 - ユーザ一覧',
            'users' => $users,
        ]);
    }

    private function handleAdminUserEdit(
        string $username,
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        UserRepository $userRepo,
    ): ResponseInterface {
        $user = $userRepo->findByUsername($username);
        if ($user === null) {
            throw new HttpNotFoundException($request);
        }

        $form = new AdminUserEditForm(
            null,
            user: $user,
            routeParser: $routeParser,
            userRepo: $userRepo,
        );
        return $this->showForm($request, $response, 'admin_user_edit.html.twig', $form);
    }

    private function handleAdminUserEditPost(
        string $username,
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        UserRepository $userRepo,
    ): ResponseInterface {
        $user = $userRepo->findByUsername($username);
        if ($user === null) {
            throw new HttpBadRequestException($request);
        }

        $form = new AdminUserEditForm(
            FormState::fromRequest($request),
            user: $user,
            routeParser: $routeParser,
            userRepo: $userRepo,
        );
        return $this->submitForm($request, $response, 'admin_user_edit.html.twig', $form);
    }

    private function handleAdminQuizList(
        ServerRequestInterface $request,
        ResponseInterface $response,
        QuizRepository $quizRepo,
    ): ResponseInterface {
        $quizzes = $quizRepo->listAll();

        return $this->render($request, $response, 'admin_quiz_list.html.twig', [
            'page_title' => '管理画面 - 問題一覧',
            'quizzes' => $quizzes,
        ]);
    }

    private function handleAdminQuizNew(
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        QuizRepository $quizRepo,
    ): ResponseInterface {
        $form = new AdminQuizNewForm(
            null,
            routeParser: $routeParser,
            quizRepo: $quizRepo,
        );
        return $this->showForm($request, $response, 'admin_quiz_new.html.twig', $form);
    }

    private function handleAdminQuizNewPost(
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        QuizRepository $quizRepo,
    ): ResponseInterface {
        $form = new AdminQuizNewForm(
            FormState::fromRequest($request),
            routeParser: $routeParser,
            quizRepo: $quizRepo,
        );
        return $this->submitForm($request, $response, 'admin_quiz_new.html.twig', $form);
    }

    private function handleAdminQuizEdit(
        string $qslug,
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        QuizRepository $quizRepo,
    ): ResponseInterface {
        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpNotFoundException($request);
        }

        $form = new AdminQuizEditForm(
            null,
            quiz: $quiz,
            routeParser: $routeParser,
            quizRepo: $quizRepo,
        );
        return $this->showForm($request, $response, 'admin_quiz_edit.html.twig', $form);
    }

    private function handleAdminQuizEditPost(
        string $qslug,
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        QuizRepository $quizRepo,
    ): ResponseInterface {
        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpBadRequestException($request);
        }

        $form = new AdminQuizEditForm(
            FormState::fromRequest($request),
            quiz: $quiz,
            routeParser: $routeParser,
            quizRepo: $quizRepo,
        );
        return $this->submitForm($request, $response, 'admin_quiz_edit.html.twig', $form);
    }

    private function handleAdminAnswerList(
        string $qslug,
        ServerRequestInterface $request,
        ResponseInterface $response,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
    ): ResponseInterface {
        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpNotFoundException($request);
        }
        $answers = $answerRepo->listByQuizId($quiz->quiz_id);

        return $this->render($request, $response, 'admin_answer_list.html.twig', [
            'page_title' => "管理画面 - 問題 #{$quiz->quiz_id} - 回答一覧",
            'quiz' => $quiz,
            'answers' => $answers,
        ]);
    }

    private function handleAdminAnswerEdit(
        string $qslug,
        string $anum,
        ServerRequestInterface $request,
        ResponseInterface $response,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
        TestcaseExecutionRepository $testcaseExecutionRepo,
    ): ResponseInterface {
        $anum = (int)$anum;
        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpNotFoundException($request);
        }
        $answer = $answerRepo->findByQuizIdAndAnswerNumber($quiz->quiz_id, answer_number: $anum);
        if ($answer === null) {
            throw new HttpNotFoundException($request);
        }
        $testcaseExecutions = $testcaseExecutionRepo->listByAnswerId($answer->answer_id);

        return $this->render($request, $response, 'admin_answer_edit.html.twig', [
            'page_title' => "管理画面 - 問題 #{$quiz->quiz_id} - 回答 #{$answer->answer_number} - 編集",
            'quiz' => $quiz,
            'answer' => $answer,
            'testcase_executions' => $testcaseExecutions,
        ]);
    }

    private function handleAdminAnswerRerunAllAnswersPost(
        string $qslug,
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
        TestcaseExecutionRepository $testcaseExecutionRepo,
        Connection $conn,
    ): ResponseInterface {
        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpNotFoundException($request);
        }

        $conn->transaction(function () use ($quiz, $answerRepo, $testcaseExecutionRepo) {
            $answerRepo->markAllAsPending($quiz->quiz_id);
            $testcaseExecutionRepo->markAllAsPendingByQuizId($quiz->quiz_id);
        });

        return $this->makeRedirectResponse($response, $routeParser->urlFor('admin_answer_list', ['qslug' => $qslug]));
    }

    private function handleAdminAnswerRerunAllTestcasesPost(
        string $qslug,
        string $anum,
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
        TestcaseExecutionRepository $testcaseExecutionRepo,
        Connection $conn,
    ): ResponseInterface {
        $anum = (int)$anum;
        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpNotFoundException($request);
        }
        $answer = $answerRepo->findByQuizIdAndAnswerNumber($quiz->quiz_id, answer_number: $anum);
        if ($answer === null) {
            throw new HttpNotFoundException($request);
        }

        $conn->transaction(function () use ($answer, $answerRepo, $testcaseExecutionRepo) {
            $answerRepo->markAsPending($answer->answer_id);
            $testcaseExecutionRepo->markAllAsPendingByAnswerId($answer->answer_id);
        });

        return $this->makeRedirectResponse($response, $routeParser->urlFor('admin_answer_list', ['qslug' => $qslug]));
    }

    private function handleAdminAnswerRerunSingleTestcasePost(
        string $qslug,
        string $anum,
        string $txid,
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
        TestcaseExecutionRepository $testcaseExecutionRepo,
        Connection $conn,
    ): ResponseInterface {
        $anum = (int)$anum;
        $txid = (int)$txid;
        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpNotFoundException($request);
        }
        $answer = $answerRepo->findByQuizIdAndAnswerNumber($quiz->quiz_id, answer_number: $anum);
        if ($answer === null) {
            throw new HttpNotFoundException($request);
        }
        $ex = $testcaseExecutionRepo->findByAnswerIdAndTestcaseExecutionId(answer_id: $answer->answer_id, testcase_execution_id: $txid);
        if ($ex === null) {
            throw new HttpNotFoundException($request);
        }

        $conn->transaction(function () use ($answer, $ex, $answerRepo, $testcaseExecutionRepo) {
            $answerRepo->markAsPending($answer->answer_id);
            $testcaseExecutionRepo->markAsPending($ex->testcase_execution_id);
        });

        return $this->makeRedirectResponse($response, $routeParser->urlFor('admin_answer_list', ['qslug' => $qslug]));
    }

    private function handleAdminTestcaseList(
        string $qslug,
        ServerRequestInterface $request,
        ResponseInterface $response,
        QuizRepository $quizRepo,
        TestcaseRepository $testcaseRepo,
    ): ResponseInterface {
        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpNotFoundException($request);
        }
        $testcases = $testcaseRepo->listByQuizId($quiz->quiz_id);

        return $this->render($request, $response, 'admin_testcase_list.html.twig', [
            'page_title' => "管理画面 - 問題 #{$quiz->quiz_id} - テストケース一覧",
            'quiz' => $quiz,
            'testcases' => $testcases,
        ]);
    }

    private function handleAdminTestcaseNew(
        string $qslug,
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
        TestcaseRepository $testcaseRepo,
        TestcaseExecutionRepository $testcaseExecutionRepo,
        Connection $conn,
    ): ResponseInterface {
        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpNotFoundException($request);
        }

        $form = new AdminTestcaseNewForm(
            null,
            quiz: $quiz,
            routeParser: $routeParser,
            answerRepo: $answerRepo,
            testcaseRepo: $testcaseRepo,
            testcaseExecutionRepo: $testcaseExecutionRepo,
            conn: $conn,
        );
        return $this->showForm($request, $response, 'admin_testcase_new.html.twig', $form);
    }

    private function handleAdminTestcaseNewPost(
        string $qslug,
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
        TestcaseRepository $testcaseRepo,
        TestcaseExecutionRepository $testcaseExecutionRepo,
        Connection $conn,
    ): ResponseInterface {
        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpBadRequestException($request);
        }

        $form = new AdminTestcaseNewForm(
            FormState::fromRequest($request),
            quiz: $quiz,
            routeParser: $routeParser,
            answerRepo: $answerRepo,
            testcaseRepo: $testcaseRepo,
            testcaseExecutionRepo: $testcaseExecutionRepo,
            conn: $conn,
        );
        return $this->submitForm($request, $response, 'admin_testcase_new.html.twig', $form);
    }

    private function handleAdminTestcaseEdit(
        string $qslug,
        string $tid,
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
        TestcaseRepository $testcaseRepo,
        TestcaseExecutionRepository $testcaseExecutionRepo,
        Connection $conn,
    ): ResponseInterface {
        $tid = (int)$tid;
        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpNotFoundException($request);
        }
        $testcase = $testcaseRepo->findByQuizIdAndTestcaseId($quiz->quiz_id, testcase_id: $tid);
        if ($testcase === null) {
            throw new HttpNotFoundException($request);
        }

        $form = new AdminTestcaseEditForm(
            null,
            testcase: $testcase,
            quiz: $quiz,
            routeParser: $routeParser,
            answerRepo: $answerRepo,
            testcaseRepo: $testcaseRepo,
            testcaseExecutionRepo: $testcaseExecutionRepo,
            conn: $conn,
        );
        return $this->showForm($request, $response, 'admin_testcase_edit.html.twig', $form);
    }

    private function handleAdminTestcaseEditPost(
        string $qslug,
        string $tid,
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
        TestcaseRepository $testcaseRepo,
        TestcaseExecutionRepository $testcaseExecutionRepo,
        Connection $conn,
    ): ResponseInterface {
        $tid = (int)$tid;
        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpNotFoundException($request);
        }
        $testcase = $testcaseRepo->findByQuizIdAndTestcaseId($quiz->quiz_id, testcase_id: $tid);
        if ($testcase === null) {
            throw new HttpNotFoundException($request);
        }

        $form = new AdminTestcaseEditForm(
            FormState::fromRequest($request),
            testcase: $testcase,
            quiz: $quiz,
            routeParser: $routeParser,
            answerRepo: $answerRepo,
            testcaseRepo: $testcaseRepo,
            testcaseExecutionRepo: $testcaseExecutionRepo,
            conn: $conn,
        );
        return $this->submitForm($request, $response, 'admin_testcase_edit.html.twig', $form);
    }

    private function handleAdminTestcaseDeletePost(
        string $qslug,
        string $tid,
        ServerRequestInterface $request,
        ResponseInterface $response,
        RouteParserInterface $routeParser,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
        TestcaseRepository $testcaseRepo,
        TestcaseExecutionRepository $testcaseExecutionRepo,
        Connection $conn,
    ): ResponseInterface {
        $tid = (int)$tid;
        $quiz = $quizRepo->findBySlug($qslug);
        if ($quiz === null) {
            throw new HttpNotFoundException($request);
        }
        $testcase = $testcaseRepo->findByQuizIdAndTestcaseId($quiz->quiz_id, testcase_id: $tid);
        if ($testcase === null) {
            throw new HttpNotFoundException($request);
        }

        $conn->transaction(function () use ($testcase, $quiz, $answerRepo, $testcaseExecutionRepo, $testcaseRepo): void {
            $answerRepo->markAllAsUpdateNeeded($quiz->quiz_id);
            $testcaseExecutionRepo->deleteByTestcaseId($testcase->testcase_id);
            $testcaseRepo->delete($testcase->testcase_id);
        });

        return $this->makeRedirectResponse($response, $routeParser->urlFor('admin_testcase_list', ['qslug' => $qslug]));
    }

    private function handleApiAnswerStatuses(
        string $aid,
        ServerRequestInterface $request,
        ResponseInterface $response,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
        TestcaseExecutionRepository $testcaseExecutionRepo,
    ): ResponseInterface {
        $aid = (int)$aid;
        $currentUser = $this->getCurrentUser($request);
        assert(
            isset($currentUser),
            'The "current_user" attribute should be set because this route has AuthRequiredMiddleware',
        );
        $answer = $answerRepo->findById($aid);
        if ($answer === null) {
            throw new HttpNotFoundException($request);
        }
        $quiz = $quizRepo->findById($answer->quiz_id);
        if ($quiz === null) {
            throw new HttpNotFoundException($request);
        }
        if (!$quiz->isFinished() && $answer->author_id !== $currentUser->user_id) {
            throw new HttpForbiddenException($request);
        }

        $testcaseExecutions = $testcaseExecutionRepo->listByAnswerId($answer->answer_id);

        $phper_token = $this->obtainPhperToken($quiz, $answer, $currentUser);

        return $this->makeJsonResponse($response, [
            'aggregated_status' => [
                'label' => $answer->execution_status->label(),
                'show_loading_indicator' => $answer->execution_status->showLoadingIndicator(),
            ],
            'testcase_executions' => array_map(fn ($ex) => [
                'id' => $ex->testcase_execution_id,
                'status' => [
                    'label' => $ex->status->label(),
                    'show_loading_indicator' => $ex->status->showLoadingIndicator(),
                ],
            ], $testcaseExecutions),
            'phper_token' => $phper_token,
        ])->withStatus(200);
    }

    private function handleApiQuizChart(
        string $qid,
        ServerRequestInterface $request,
        ResponseInterface $response,
        QuizRepository $quizRepo,
        AnswerRepository $answerRepo,
    ): ResponseInterface {
        $qid = (int)$qid;
        $quiz = $quizRepo->findById($qid);
        if ($quiz === null) {
            return $this->makeJsonResponse($response, [
                'error' => 'not_found',
            ])->withStatus(404);
        }
        if ($quiz->isRankingHidden()) {
            return $this->makeJsonResponse($response, [
                'error' => 'forbidden',
            ])->withStatus(403);
        }

        $correctAnswers = $answerRepo->listAllCorrectAnswers($quiz->quiz_id);

        $stats = [];
        foreach ($correctAnswers as $answer) {
            if ($answer->submitted_at < $quiz->started_at) {
                continue;
            }
            if (!isset($stats[$answer->author_id])) {
                $stats[$answer->author_id]['user'] = [
                    'name' => $answer->author_name,
                    'is_admin' => $answer->author_is_admin,
                ];
            }
            $stats[$answer->author_id]['scores'][] = [
                'submitted_at' => $answer->submitted_at->getTimestamp(),
                'code_size' => $answer->code_size,
            ];
        }
        usort($stats, function ($a, $b) {
            $aBestScore = min(array_column($a['scores'], 'code_size'));
            $bBestScore = min(array_column($b['scores'], 'code_size'));
            return $aBestScore <=> $bBestScore;
        });

        return $this->makeJsonResponse($response, [
            'stats' => array_values($stats),
        ])
            ->withHeader('Cache-Control', 'max-age=10')
            ->withStatus(200);
    }

    private function obtainPhperToken(Quiz $quiz, Answer $answer, User $currentUser): ?string
    {
        if ($answer->author_id !== $currentUser->user_id) {
            return null;
        }
        if ($answer->execution_status !== AggregatedExecutionStatus::OK) {
            return null;
        }
        if ($quiz->birdie_code_size === null) {
            return null;
        }
        if ($answer->code_size > $quiz->birdie_code_size) {
            return null;
        }
        return '#albatros-' . md5('KORE-HA-TEKITO-NA-SORUTO-DESU' . $quiz->slug);
    }

    private function makeJsonResponse(ResponseInterface $response, mixed $data): ResponseInterface
    {
        $payload = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function makeRedirectResponse(ResponseInterface $response, string $path, int $status = 303): ResponseInterface
    {
        return $response
            ->withStatus($status)
            ->withHeader('Location', $path);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $template,
        array $context,
    ): ResponseInterface {
        return Twig::fromRequest($request)->render(
            $response,
            $template,
            $context + ['site_name' => $this->config->siteName],
        );
    }

    private function showForm(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $template,
        FormBase $form,
    ): ResponseInterface {
        return $this->render($request, $response, $template, [
            'page_title' => $form->pageTitle(),
            'form' => $form->toTemplateVars(),
        ] + $form->getRenderContext());
    }

    private function submitForm(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $template,
        FormBase $form,
    ): ResponseInterface {
        try {
            $form->submit();
            return $this->makeRedirectResponse($response, $form->redirectUrl());
        } catch (FormSubmissionFailureException $e) {
            return $this->showForm($request, $response, $template, $form)
                ->withStatus($e->getCode());
        }
    }

    private function getCurrentUser(ServerRequestInterface $request): ?User
    {
        $currentUser = $request->getAttribute('current_user');
        assert(
            $currentUser === null || $currentUser instanceof User,
            'The "current_user" attribute should be set by CurrentUserMiddleware if available',
        );
        return $currentUser;
    }
}
