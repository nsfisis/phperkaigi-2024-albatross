<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Migrations;

use Nsfisis\Albatross\Database\Connection;

final class MigrationManager
{
    public function __construct(
        private readonly Connection $conn,
    ) {
    }

    public function execute(): void
    {
        $this->conn->transaction(function () {
            $version = $this->fetchSchemaVersion();
            while (method_exists($this, "execute$version")) {
                $method = "execute$version";
                // @phpstan-ignore-next-line
                $this->$method();
                $this->conn
                    ->query()
                    ->insert('migrations')
                    ->values([])
                    ->execute();
                $version++;
            }
        });
    }

    private function fetchSchemaVersion(): int
    {
        $this->conn->query()->schema(<<<EOSQL
            CREATE TABLE IF NOT EXISTS migrations (
                migration_id SERIAL PRIMARY KEY
            );
        EOSQL);
        $result = $this->conn
            ->query()
            ->select('migrations')
            ->fields(['COALESCE(MAX(migration_id), 0) + 1 AS schema_version'])
            ->first()
            ->execute();
        assert(isset($result['schema_version']));
        return (int) $result['schema_version'];
    }

    /**
     * Create the initial schema.
     */
    private function execute1(): void
    {
        $this->conn->query()->schema(<<<EOSQL
            CREATE TABLE IF NOT EXISTS quizzes (
                quiz_id SERIAL PRIMARY KEY,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                title TEXT NOT NULL,
                summary TEXT NOT NULL,
                input_description TEXT NOT NULL,
                output_description TEXT NOT NULL,
                expected_result TEXT NOT NULL,
                example_code TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS answers (
                answer_id SERIAL PRIMARY KEY,
                quiz_id INTEGER NOT NULL,
                answer_number INTEGER NOT NULL,
                submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                author_name TEXT NOT NULL,
                code TEXT NOT NULL,
                code_size INTEGER NOT NULL,
                execution_status INTEGER NOT NULL,
                execution_stdout TEXT,
                execution_stderr TEXT,
                UNIQUE (quiz_id, answer_number),
                FOREIGN KEY (quiz_id) REFERENCES quizzes (quiz_id)
            );
        EOSQL);
    }

    /**
     * Add "slug" column to quizzes table.
     */
    private function execute2(): void
    {
        $this->conn->query()->schema(<<<EOSQL
            ALTER TABLE quizzes ADD COLUMN slug VARCHAR(32);
            UPDATE quizzes SET slug = title;
            ALTER TABLE quizzes ALTER COLUMN slug SET NOT NULL;
            ALTER TABLE quizzes ADD CONSTRAINT uniq_slug UNIQUE (slug);
        EOSQL);
    }

    /**
     * Add "birdie_code_size" column to quizzes table.
     */
    private function execute3(): void
    {
        $this->conn->query()->schema(<<<EOSQL
            ALTER TABLE quizzes ADD COLUMN birdie_code_size INTEGER;
        EOSQL);
    }

    /**
     * Remove "input_description" and "summary" columns from quizzes table. Rename "output_description" to "description".
     */
    private function execute4(): void
    {
        $this->conn->query()->schema(<<<EOSQL
            ALTER TABLE quizzes DROP COLUMN input_description;
            ALTER TABLE quizzes DROP COLUMN summary;
            ALTER TABLE quizzes RENAME COLUMN output_description TO description;
        EOSQL);
    }

    /**
     * Add "started_at", "answers_hidden_at" and "finished_at" columns to quizzes table.
     */
    private function execute5(): void
    {
        $this->conn->query()->schema(<<<EOSQL
            ALTER TABLE quizzes ADD COLUMN started_at TIMESTAMP;
            ALTER TABLE quizzes ADD COLUMN answers_hidden_at TIMESTAMP;
            ALTER TABLE quizzes ADD COLUMN finished_at TIMESTAMP;
        EOSQL);
    }

    /**
     * Create users table.
     */
    private function execute6(): void
    {
        $this->conn->query()->schema(<<<EOSQL
            CREATE TABLE IF NOT EXISTS users (
                user_id SERIAL PRIMARY KEY,
                username VARCHAR(64) NOT NULL,
                password_hash VARCHAR(256) NOT NULL,
                is_admin BOOLEAN NOT NULL DEFAULT FALSE,
                UNIQUE (username)
            );
        EOSQL);
    }

    /**
     * Migrate from author_name to author_id in answers table.
     */
    private function execute7(): void
    {
        $this->conn->query()->schema(<<<EOSQL
            ALTER TABLE answers DROP COLUMN author_name;
            TRUNCATE TABLE answers;
            ALTER TABLE answers ADD COLUMN author_id INTEGER NOT NULL;
            ALTER TABLE answers ADD CONSTRAINT fk_author_id FOREIGN KEY (author_id) REFERENCES users (user_id);
        EOSQL);
    }

    /**
     * Rename answers_hidden_at to ranking_hidden_at in quizzes table.
     */
    private function execute8(): void
    {
        $this->conn->query()->schema(<<<EOSQL
            ALTER TABLE quizzes RENAME COLUMN answers_hidden_at TO ranking_hidden_at;
        EOSQL);
    }

    /**
     * Make "started_at", "ranking_hidden_at" and "finished_at" columns not null.
     */
    private function execute9(): void
    {
        $this->conn->query()->schema(<<<EOSQL
            UPDATE quizzes SET started_at = CURRENT_TIMESTAMP;
            UPDATE quizzes SET ranking_hidden_at = CURRENT_TIMESTAMP;
            UPDATE quizzes SET finished_at = CURRENT_TIMESTAMP;
            ALTER TABLE quizzes ALTER COLUMN started_at SET NOT NULL;
            ALTER TABLE quizzes ALTER COLUMN ranking_hidden_at SET NOT NULL;
            ALTER TABLE quizzes ALTER COLUMN finished_at SET NOT NULL;
        EOSQL);
    }

    /**
     * Remove "password_hash" column from "users" table.
     */
    private function execute10(): void
    {
        $this->conn->query()->schema(<<<EOSQL
            ALTER TABLE users DROP COLUMN password_hash;
        EOSQL);
    }

    /**
     * Implement multi-testcases.
     *
     * - Create "testcases" table and "testcase_executions" table.
     * - Remove "expected_result" column from "quizzes" table.
     * - Remove "execution_stdout" and "execution_stderr" columns from "answers" table.
     */
    private function execute11(): void
    {
        $this->conn->query()->schema(<<<EOSQL
            CREATE TABLE IF NOT EXISTS testcases (
                testcase_id SERIAL PRIMARY KEY,
                quiz_id INTEGER NOT NULL,
                input TEXT NOT NULL,
                expected_result TEXT NOT NULL,
                FOREIGN KEY (quiz_id) REFERENCES quizzes (quiz_id)
            );
            CREATE TABLE IF NOT EXISTS testcase_executions (
                testcase_execution_id SERIAL PRIMARY KEY,
                testcase_id INTEGER NOT NULL,
                answer_id INTEGER NOT NULL,
                status INTEGER NOT NULL,
                stdout TEXT,
                stderr TEXT,
                FOREIGN KEY (testcase_id) REFERENCES testcases (testcase_id),
                FOREIGN KEY (answer_id) REFERENCES answers (answer_id)
            );

            INSERT INTO testcases (quiz_id, input, expected_result)
                SELECT quiz_id, '', expected_result FROM quizzes;
            ALTER TABLE quizzes DROP COLUMN expected_result;

            INSERT INTO testcase_executions (testcase_id, answer_id, status, stdout, stderr)
                SELECT testcases.testcase_id, answers.answer_id, answers.execution_status, answers.execution_stdout, answers.execution_stderr
                FROM answers
                INNER JOIN testcases ON testcases.quiz_id = answers.quiz_id;
            ALTER TABLE answers DROP COLUMN execution_stdout;
            ALTER TABLE answers DROP COLUMN execution_stderr;
        EOSQL);
    }

    /**
     * Migrate value of "execution_status" in "answers" table.
     */
    private function execute12(): void
    {
        $this->conn->query()->schema(<<<EOSQL
            UPDATE answers SET execution_status = 1 WHERE execution_status = 0 OR execution_status = 1;
            UPDATE answers SET execution_status = 2 WHERE execution_status BETWEEN 2 AND 5;
            UPDATE answers SET execution_status = 3 WHERE execution_status = 6;
        EOSQL);
    }
}
