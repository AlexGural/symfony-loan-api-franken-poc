<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260301200000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create loans and payments tables';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE loans (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                principal_amount BIGINT NOT NULL,
                currency VARCHAR(3) NOT NULL,
                annual_interest_rate NUMERIC(5, 2) NOT NULL,
                term_months INT NOT NULL,
                initial_monthly_payment BIGINT NOT NULL,
                remaining_balance BIGINT NOT NULL,
                paid_months INT NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_loans_user_id ON loans (user_id)');

        $this->addSql('COMMENT ON COLUMN loans.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql(<<<'SQL'
            CREATE TABLE payments (
                id UUID NOT NULL,
                loan_id UUID NOT NULL,
                amount BIGINT NOT NULL,
                currency VARCHAR(3) NOT NULL,
                remaining_balance_after BIGINT NOT NULL,
                payment_number INT NOT NULL,
                paid_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_payments_loan_id ON payments (loan_id)');

        $this->addSql('COMMENT ON COLUMN payments.paid_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('ALTER TABLE loans ADD CONSTRAINT fk_loans_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE payments ADD CONSTRAINT fk_payments_loan_id FOREIGN KEY (loan_id) REFERENCES loans (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payments DROP CONSTRAINT fk_payments_loan_id');
        $this->addSql('ALTER TABLE loans DROP CONSTRAINT fk_loans_user_id');
        $this->addSql('DROP TABLE payments');
        $this->addSql('DROP TABLE loans');
    }
}
