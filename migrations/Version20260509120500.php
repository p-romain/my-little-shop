<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509120500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow optional shop address and coordinates';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop ALTER address DROP NOT NULL');
        $this->addSql('ALTER TABLE shop ALTER latitude DROP NOT NULL');
        $this->addSql('ALTER TABLE shop ALTER longitude DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE shop SET address = '' WHERE address IS NULL");
        $this->addSql('UPDATE shop SET latitude = 0 WHERE latitude IS NULL');
        $this->addSql('UPDATE shop SET longitude = 0 WHERE longitude IS NULL');
        $this->addSql('ALTER TABLE shop ALTER address SET NOT NULL');
        $this->addSql('ALTER TABLE shop ALTER latitude SET NOT NULL');
        $this->addSql('ALTER TABLE shop ALTER longitude SET NOT NULL');
    }
}
