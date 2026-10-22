<?php

declare(strict_types=1);

namespace App\Migrations;

use Prism\Dbal\Schema\Column;
use Prism\Dbal\Schema\ForeignKey;
use Prism\Dbal\Schema\ForeignKeyAction;
use Prism\Dbal\Schema\Index;
use Prism\Dbal\Schema\Table;
use Prism\Migrations\AbstractMigration;
use Prism\Migrations\MigrationBuilder;

// Généré par db:migrations:diff : à relire avant de l'exécuter.
final class Version20261022193204 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(MigrationBuilder $m): void
    {
        $m->createTable(new Table('customer', [
            new Column('id', 'integer', autoincrement: true),
            new Column('name', 'string', length: 120),
            new Column('email', 'string', length: 180),
            new Column('city', 'string', nullable: true, length: 80),
        ], primaryKey: ['id'], indexes: [new Index('uniq_customer_45b166be', ['email'], unique: true)]));
        $m->createTable(new Table('invoice', [
            new Column('id', 'integer', autoincrement: true),
            new Column('number', 'string', length: 20),
            new Column('issued_at', 'datetime_immutable'),
            new Column('total', 'decimal', precision: 12, scale: 2),
            new Column('paid', 'boolean'),
            new Column('order_id', 'integer'),
        ], primaryKey: ['id'], indexes: [new Index('uniq_invoice_240941ec', ['number'], unique: true), new Index('uniq_invoice_41a98438', ['order_id'], unique: true)], foreignKeys: [new ForeignKey('fk_invoice_41a98438', ['order_id'], 'order', ['id'], ForeignKeyAction::Restrict)]));
        $m->createTable(new Table('order', [
            new Column('id', 'integer', autoincrement: true),
            new Column('reference', 'string', length: 20),
            new Column('placed_at', 'datetime_immutable'),
            new Column('status', 'string', length: 20),
            new Column('customer_id', 'integer'),
        ], primaryKey: ['id'], indexes: [new Index('idx_order_63656d22', ['customer_id'])], foreignKeys: [new ForeignKey('fk_order_63656d22', ['customer_id'], 'customer', ['id'], ForeignKeyAction::Restrict)]));
        $m->createTable(new Table('order_line', [
            new Column('id', 'integer', autoincrement: true),
            new Column('quantity', 'integer'),
            new Column('unit_price', 'decimal', precision: 10, scale: 2),
            new Column('order_id', 'integer'),
            new Column('product_id', 'integer'),
        ], primaryKey: ['id'], indexes: [new Index('idx_order_line_ed8c2e09', ['order_id']), new Index('idx_order_line_18c101cf', ['product_id'])], foreignKeys: [new ForeignKey('fk_order_line_ed8c2e09', ['order_id'], 'order', ['id'], ForeignKeyAction::Restrict), new ForeignKey('fk_order_line_18c101cf', ['product_id'], 'product', ['id'], ForeignKeyAction::Restrict)]));
        $m->createTable(new Table('product', [
            new Column('id', 'integer', autoincrement: true),
            new Column('sku', 'string', length: 40),
            new Column('name', 'string', length: 120),
            new Column('price', 'decimal', precision: 10, scale: 2),
        ], primaryKey: ['id']));
    }

    public function down(MigrationBuilder $m): void
    {
        $m->dropTable('product');
        $m->dropTable('order_line');
        $m->dropTable('order');
        $m->dropTable('invoice');
        $m->dropTable('customer');
    }
}
