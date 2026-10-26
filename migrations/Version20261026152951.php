<?php

declare(strict_types=1);

namespace App\Migrations;

use Prism\Dbal\Schema\Column;
use Prism\Dbal\Schema\Index;
use Prism\Dbal\Schema\Table;
use Prism\Migrations\AbstractMigration;
use Prism\Migrations\MigrationBuilder;

// Généré par db:migrations:diff : à relire avant de l'exécuter.
final class Version20261026152951 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(MigrationBuilder $m): void
    {
        $m->createTable(new Table('company', [
            new Column('id', 'integer', autoincrement: true),
            new Column('slug', 'string', length: 40),
            new Column('name', 'string', length: 120),
        ], primaryKey: ['id'], indexes: [new Index('uniq_company_95f72c7f', ['slug'], unique: true)]));
        $m->createTable(new Table('prism_audit_log', [
            new Column('id', 'bigint', autoincrement: true),
            new Column('entity_class', 'string', length: 255),
            new Column('entity_id', 'string', length: 64),
            new Column('tenant_id', 'string', nullable: true, length: 64),
            new Column('action', 'string', length: 16),
            new Column('changes', 'json'),
            new Column('actor', 'string', nullable: true, length: 255),
            new Column('request_id', 'string', nullable: true, length: 64),
            new Column('system', 'boolean'),
            new Column('occurred_at', 'datetime_immutable'),
        ], primaryKey: ['id'], indexes: [new Index('idx_prism_audit_log_d54dfbff', ['entity_class', 'entity_id']), new Index('idx_prism_audit_log_ceee483a', ['occurred_at']), new Index('idx_prism_audit_log_a350a21c', ['tenant_id'])]));
        $m->dropIndex('customer', 'uniq_customer_45b166be');
        $m->addColumn('customer', new Column('deleted_at', 'datetime_immutable', nullable: true));
        $m->addColumn('customer', new Column('tenant_id', 'integer'));
        $m->createIndex('customer', ['tenant_id', 'email'], unique: true, name: 'uniq_customer_de1b1f18', whereNull: 'deleted_at');
        $m->dropIndex('invoice', 'uniq_invoice_240941ec');
        $m->addColumn('invoice', new Column('tenant_id', 'integer'));
        $m->createIndex('invoice', ['tenant_id', 'number'], unique: true, name: 'uniq_invoice_9280039d');
        $m->addColumn('order', new Column('created_at', 'datetime_immutable'));
        $m->addColumn('order', new Column('updated_at', 'datetime_immutable', nullable: true));
        $m->addColumn('order', new Column('created_by', 'string', nullable: true, length: 180));
        $m->addColumn('order', new Column('updated_by', 'string', nullable: true, length: 180));
        $m->addColumn('order', new Column('tenant_id', 'integer'));
        $m->createIndex('order', ['tenant_id'], name: 'idx_order_64ca480a');
        $m->addColumn('order_line', new Column('tenant_id', 'integer'));
        $m->createIndex('order_line', ['tenant_id'], name: 'idx_order_line_6c5b43ef');
        $m->addColumn('product', new Column('tenant_id', 'integer', nullable: true));
        $m->createIndex('product', ['tenant_id'], name: 'idx_product_3962f956');
    }

    public function down(MigrationBuilder $m): void
    {
        $m->dropIndex('product', 'idx_product_3962f956');
        $m->dropColumn('product', 'tenant_id');
        $m->dropIndex('order_line', 'idx_order_line_6c5b43ef');
        $m->dropColumn('order_line', 'tenant_id');
        $m->dropIndex('order', 'idx_order_64ca480a');
        $m->dropColumn('order', 'tenant_id');
        $m->dropColumn('order', 'updated_by');
        $m->dropColumn('order', 'created_by');
        $m->dropColumn('order', 'updated_at');
        $m->dropColumn('order', 'created_at');
        $m->dropIndex('invoice', 'uniq_invoice_9280039d');
        $m->dropColumn('invoice', 'tenant_id');
        $m->createIndex('invoice', ['number'], unique: true, name: 'uniq_invoice_240941ec');
        $m->dropIndex('customer', 'uniq_customer_de1b1f18');
        $m->dropColumn('customer', 'tenant_id');
        $m->dropColumn('customer', 'deleted_at');
        $m->createIndex('customer', ['email'], unique: true, name: 'uniq_customer_45b166be');
        $m->dropTable('prism_audit_log');
        $m->dropTable('company');
    }
}
