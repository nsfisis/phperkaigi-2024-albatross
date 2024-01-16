<?php

declare(strict_types=1);

namespace Nsfisis\Albatross;

use Nsfisis\Albatross\Database\Connection;
use Nsfisis\Albatross\Migrations\MigrationManager;
use Nsfisis\Albatross\Repositories\AnswerRepository;
use Nsfisis\Albatross\Repositories\QuizRepository;
use Nsfisis\Albatross\Repositories\UserRepository;

final class AlbCtl
{
    private Connection $conn;

    public function __construct(
        Config $config,
    ) {
        $this->conn = new Connection(
            driver: 'pgsql',
            host: $config->dbHost,
            port: $config->dbPort,
            name: $config->dbName,
            user: $config->dbUser,
            password: $config->dbPassword,
            max_tries: 10,
            sleep_sec: 3,
        );
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): void
    {
        match ($argv[1] ?? 'help') {
            'migrate' => $this->runMigrate(),
            'promote' => $this->runPromote(),
            'deluser' => $this->runDeleteUser(),
            'delquiz' => $this->runDeleteQuiz(),
            default => $this->runHelp(),
        };
    }

    private function runMigrate(): void
    {
        $migration_manager = new MigrationManager($this->conn);
        $migration_manager->execute();
    }

    private function runPromote(): void
    {
        echo "Username: ";
        $username = trim((string)fgets(STDIN));

        $user_repo = new UserRepository($this->conn);
        $user = $user_repo->findByUsername($username);
        if ($user === null) {
            echo "User '$username' not found.\n";
            return;
        }
        $user_repo->update(
            user_id: $user->user_id,
            is_admin: true,
        );
    }

    private function runDeleteUser(): void
    {
        echo "Username: ";
        $username = trim((string)fgets(STDIN));

        $user_repo = new UserRepository($this->conn);
        $answer_repo = new AnswerRepository($this->conn);

        $this->conn->transaction(function () use ($user_repo, $answer_repo, $username) {
            $user = $user_repo->findByUsername($username);
            if ($user === null) {
                echo "User '$username' not found.\n";
                return;
            }
            $answer_repo->deleteAllByUserId($user->user_id);
            $user_repo->delete($user->user_id);
        });
        // It is unnecessary to destroy existing sessions here because
        // CurrentUserMiddleware will check whether the user exists or not.
    }

    private function runDeleteQuiz(): void
    {
        echo "Quiz ID: ";
        $quiz_id = (int)trim((string)fgets(STDIN));

        $answer_repo = new AnswerRepository($this->conn);
        $quiz_repo = new QuizRepository($this->conn);

        $this->conn->transaction(function () use ($answer_repo, $quiz_repo, $quiz_id) {
            $answer_repo->deleteAllByQuizId($quiz_id);
            $quiz_repo->delete($quiz_id);
        });
    }

    private function runHelp(): void
    {
        echo <<<EOS
        Usage: albctl <command>

        Commands:
          migrate  Run database migrations.
          promote  Promote a user to administrator.
          deluser  Delete a user.
          delquiz  Delete a quiz.
        EOS;
    }
}
