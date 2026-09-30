<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260930083000 extends AbstractMigration
{
    public function getDescription():string{return 'Accusés de notification et index de recherche métier';}
    public function up(Schema $schema):void
    {
        $timestamp=$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform?'TIMESTAMP(0) WITHOUT TIME ZONE':'DATETIME';
        $this->addSql('CREATE TABLE notification_receipt (id VARCHAR(180) NOT NULL PRIMARY KEY, delivered_at '.$timestamp.' NOT NULL)');
        $this->addSql('CREATE INDEX idx_product_category_status ON product (category, status)');
        $this->addSql('CREATE INDEX idx_purchase_expiration ON purchase (reservation_status, expires_at)');
        $this->addSql('CREATE INDEX idx_stock_variant_date ON stock_adjustment (variant_id, created_at)');
        if($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform){
            $this->addSql('ALTER TABLE variant ADD CONSTRAINT valid_stock CHECK (physical >= 0 AND reserved >= 0 AND reserved <= physical)');
            $this->addSql('ALTER TABLE variant ADD CONSTRAINT valid_price CHECK (price >= 0)');
        }
    }
    public function down(Schema $schema):void
    {
        $this->addSql('DROP TABLE notification_receipt');$this->addSql('DROP INDEX idx_product_category_status');$this->addSql('DROP INDEX idx_purchase_expiration');$this->addSql('DROP INDEX idx_stock_variant_date');
        if($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform){$this->addSql('ALTER TABLE variant DROP CONSTRAINT valid_stock');$this->addSql('ALTER TABLE variant DROP CONSTRAINT valid_price');}
    }
}
