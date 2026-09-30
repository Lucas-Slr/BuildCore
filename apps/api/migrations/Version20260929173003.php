<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260929173003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalogue, clients, commandes, stock, idempotence et messages asynchrones';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? 'CREATE TABLE customer (id VARCHAR(36) NOT NULL, email VARCHAR(180) NOT NULL, name VARCHAR(120) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, addresses JSON NOT NULL, cart JSON NOT NULL, builds JSON NOT NULL, PRIMARY KEY (id))' : 'CREATE TABLE customer (id VARCHAR(36) NOT NULL, email VARCHAR(180) NOT NULL, name VARCHAR(120) NOT NULL, password VARCHAR(255) NOT NULL, roles CLOB NOT NULL, addresses CLOB NOT NULL, cart CLOB NOT NULL, builds CLOB NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_81398E09E7927C74 ON customer (email)');
        $this->addSql($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? 'CREATE TABLE product (id VARCHAR(36) NOT NULL, name VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, brand VARCHAR(80) NOT NULL, category VARCHAR(40) NOT NULL, description TEXT NOT NULL, summary VARCHAR(280) NOT NULL, status VARCHAR(20) NOT NULL, specs JSON NOT NULL, images JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))' : 'CREATE TABLE product (id VARCHAR(36) NOT NULL, name VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, brand VARCHAR(80) NOT NULL, category VARCHAR(40) NOT NULL, description CLOB NOT NULL, summary VARCHAR(280) NOT NULL, status VARCHAR(20) NOT NULL, specs CLOB NOT NULL, images CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D34A04AD989D9B62 ON product (slug)');
        $this->addSql($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? 'CREATE TABLE purchase (id VARCHAR(36) NOT NULL, number VARCHAR(32) NOT NULL, email VARCHAR(180) NOT NULL, lines JSON NOT NULL, shipping_address JSON NOT NULL, billing_address JSON NOT NULL, delivery VARCHAR(20) NOT NULL, shipping INTEGER NOT NULL, total INTEGER NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(30) NOT NULL, payment_status VARCHAR(30) NOT NULL, reservation_status VARCHAR(30) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, stripe_session VARCHAR(255) DEFAULT NULL, payment_intent VARCHAR(255) DEFAULT NULL, stripe_refund VARCHAR(255) DEFAULT NULL, checkout_url TEXT DEFAULT NULL, tracking VARCHAR(180) NOT NULL, idempotency_key VARCHAR(180) NOT NULL, history JSON NOT NULL, customer_id VARCHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_6117D13B9395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) NOT DEFERRABLE INITIALLY IMMEDIATE)' : 'CREATE TABLE purchase (id VARCHAR(36) NOT NULL, number VARCHAR(32) NOT NULL, email VARCHAR(180) NOT NULL, lines CLOB NOT NULL, shipping_address CLOB NOT NULL, billing_address CLOB NOT NULL, delivery VARCHAR(20) NOT NULL, shipping INTEGER NOT NULL, total INTEGER NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(30) NOT NULL, payment_status VARCHAR(30) NOT NULL, reservation_status VARCHAR(30) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, stripe_session VARCHAR(255) DEFAULT NULL, payment_intent VARCHAR(255) DEFAULT NULL, stripe_refund VARCHAR(255) DEFAULT NULL, checkout_url CLOB DEFAULT NULL, tracking VARCHAR(180) NOT NULL, idempotency_key VARCHAR(180) NOT NULL, history CLOB NOT NULL, customer_id VARCHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_6117D13B9395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6117D13B96901F54 ON purchase (number)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6117D13BFF83E42E ON purchase (stripe_session)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6117D13B7FD1C147 ON purchase (idempotency_key)');
        $this->addSql('CREATE INDEX IDX_6117D13B9395C3F3 ON purchase (customer_id)');
        $this->addSql($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? 'CREATE TABLE stock_adjustment (id VARCHAR(36) NOT NULL, variant_id VARCHAR(36) NOT NULL, before_quantity INTEGER NOT NULL, after_quantity INTEGER NOT NULL, reason VARCHAR(280) NOT NULL, actor VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))' : 'CREATE TABLE stock_adjustment (id VARCHAR(36) NOT NULL, variant_id VARCHAR(36) NOT NULL, before_quantity INTEGER NOT NULL, after_quantity INTEGER NOT NULL, reason VARCHAR(280) NOT NULL, actor VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? 'CREATE TABLE variant (id VARCHAR(36) NOT NULL, sku VARCHAR(80) NOT NULL, name VARCHAR(120) NOT NULL, price INTEGER NOT NULL, currency VARCHAR(3) NOT NULL, physical INTEGER NOT NULL, reserved INTEGER NOT NULL, low_threshold INTEGER NOT NULL, weight INTEGER NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, product_id VARCHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_F143BFAD4584665A FOREIGN KEY (product_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE)' : 'CREATE TABLE variant (id VARCHAR(36) NOT NULL, sku VARCHAR(80) NOT NULL, name VARCHAR(120) NOT NULL, price INTEGER NOT NULL, currency VARCHAR(3) NOT NULL, physical INTEGER NOT NULL, reserved INTEGER NOT NULL, low_threshold INTEGER NOT NULL, weight INTEGER NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, product_id VARCHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_F143BFAD4584665A FOREIGN KEY (product_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F143BFADF9038C4 ON variant (sku)');
        $this->addSql('CREATE INDEX IDX_F143BFAD4584665A ON variant (product_id)');
        $this->addSql($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? 'CREATE TABLE webhook_receipt (id VARCHAR(255) NOT NULL, processed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))' : 'CREATE TABLE webhook_receipt (id VARCHAR(255) NOT NULL, processed_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? 'CREATE TABLE messenger_messages (id BIGSERIAL PRIMARY KEY, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL)' : 'CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        
        
        $this->addSql('DROP TABLE purchase');
        $this->addSql('DROP TABLE stock_adjustment');
        $this->addSql('DROP TABLE variant');
        $this->addSql('DROP TABLE webhook_receipt');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP TABLE customer');
        $this->addSql('DROP TABLE product');
    }
}
