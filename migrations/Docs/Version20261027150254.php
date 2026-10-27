<?php

declare(strict_types=1);

namespace App\Migrations\Docs;

use Prism\Dbal\Schema\Column;
use Prism\Dbal\Schema\Index;
use Prism\Dbal\Schema\IndexKind;
use Prism\Dbal\Schema\Table;
use Prism\Dbal\Schema\VectorDistance;
use Prism\Migrations\AbstractMigration;
use Prism\Migrations\MigrationBuilder;

// Généré par db:migrations:diff : à relire avant de l'exécuter.
final class Version20261027150254 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Documentation search: sections with their vector and full-text index (pillar 4)';
    }

    public function up(MigrationBuilder $m): void
    {
        $m->createTable(new Table('doc_section', [
            new Column('id', 'integer', autoincrement: true),
            new Column('embedding', 'vector', nullable: true, length: 768),
            new Column('path', 'string', length: 200),
            new Column('title', 'string', length: 255),
            new Column('content', 'text'),
            new Column('embedding_stale', 'boolean', default: true),
            new Column('embedding_model', 'string', nullable: true, length: 255),
        ], primaryKey: ['id'], indexes: [new Index('uniq_doc_section_c28d56fd', ['path'], unique: true), new Index('ftx_doc_section_5bf405dc', ['title', 'content'], kind: IndexKind::FullText, language: 'english'), new Index('vec_doc_section_63fc03a3', ['embedding'], kind: IndexKind::Vector, distance: VectorDistance::Cosine, m: 16, efConstruction: 64)]));
    }

    public function down(MigrationBuilder $m): void
    {
        $m->dropTable('doc_section');
    }
}
