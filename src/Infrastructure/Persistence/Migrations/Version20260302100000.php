<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260302100000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add cross-currency payment fields to payments table';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payments ADD original_amount BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE payments ADD original_currency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE payments ADD exchange_rate NUMERIC(12, 6) DEFAULT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payments DROP COLUMN original_amount');
        $this->addSql('ALTER TABLE payments DROP COLUMN original_currency');
        $this->addSql('ALTER TABLE payments DROP COLUMN exchange_rate');
    }
}
