<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509141047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial creation of the database';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE stock_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE stock (id INT NOT NULL, product_id INT NOT NULL, shop_id INT NOT NULL, quantity INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4B3656604584665A ON stock (product_id)');
        $this->addSql('CREATE INDEX IDX_4B3656604D16C4DD ON stock (shop_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4B3656604584665A4D16C4DD ON stock (product_id, shop_id)');
        $this->addSql('ALTER TABLE stock ADD CONSTRAINT FK_4B3656604584665A FOREIGN KEY (product_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE stock ADD CONSTRAINT FK_4B3656604D16C4DD FOREIGN KEY (shop_id) REFERENCES shop (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE shop ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE shop ALTER latitude DROP NOT NULL');
        $this->addSql('ALTER TABLE shop ALTER longitude DROP NOT NULL');
        $this->addSql('ALTER TABLE shop ALTER address DROP NOT NULL');
        $this->addSql('ALTER INDEX idx_ac6a4ca783e3463 RENAME TO IDX_AC6A4CA2783E3463');
        $this->addSql('ALTER TABLE users ALTER id DROP DEFAULT');
        $this->addSql('ALTER INDEX uniq_identifier_email RENAME TO UNIQ_1483A5E9E7927C74');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE stock_id_seq CASCADE');
        $this->addSql('ALTER TABLE stock DROP CONSTRAINT FK_4B3656604584665A');
        $this->addSql('ALTER TABLE stock DROP CONSTRAINT FK_4B3656604D16C4DD');
        $this->addSql('DROP TABLE stock');
        $this->addSql('CREATE SEQUENCE shop_id_seq');
        $this->addSql('SELECT setval(\'shop_id_seq\', (SELECT MAX(id) FROM shop))');
        $this->addSql('ALTER TABLE shop ALTER id SET DEFAULT nextval(\'shop_id_seq\')');
        $this->addSql('ALTER TABLE shop ALTER latitude SET NOT NULL');
        $this->addSql('ALTER TABLE shop ALTER longitude SET NOT NULL');
        $this->addSql('ALTER TABLE shop ALTER address SET NOT NULL');
        $this->addSql('ALTER INDEX idx_ac6a4ca2783e3463 RENAME TO idx_ac6a4ca783e3463');
        $this->addSql('CREATE SEQUENCE product_id_seq');
        $this->addSql('SELECT setval(\'product_id_seq\', (SELECT MAX(id) FROM product))');
        $this->addSql('ALTER TABLE product ALTER id SET DEFAULT nextval(\'product_id_seq\')');
        $this->addSql('CREATE SEQUENCE users_id_seq');
        $this->addSql('SELECT setval(\'users_id_seq\', (SELECT MAX(id) FROM users))');
        $this->addSql('ALTER TABLE users ALTER id SET DEFAULT nextval(\'users_id_seq\')');
        $this->addSql('ALTER INDEX uniq_1483a5e9e7927c74 RENAME TO uniq_identifier_email');
    }
}
