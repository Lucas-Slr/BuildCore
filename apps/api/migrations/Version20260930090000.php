<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260930090000 extends AbstractMigration
{
    public function getDescription(): string { return 'Conserver les configurations ajoutées au panier'; }
    public function up(Schema $schema): void
    {
        $type = $this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? 'JSON' : 'CLOB';
        $this->addSql('ALTER TABLE customer ADD COLUMN cart_groups '.$type." NOT NULL DEFAULT '[]'");
    }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE customer DROP COLUMN cart_groups'); }
}
