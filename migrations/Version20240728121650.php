<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240728121650 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add category_id to post table with a foreign key constraint.';
    }

    public function up(Schema $schema): void
    {

        // Updating existing records to have a valid category_id (assuming there's a category with id = 1)
        $this->addSql('UPDATE post SET category_id = 2 WHERE category_id = 0');

        // Adding the foreign key constraint
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8D12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('CREATE INDEX IDX_5A8A6C8D12469DE2 ON post (category_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8D12469DE2');
        $this->addSql('DROP INDEX IDX_5A8A6C8D12469DE2 ON post');
        $this->addSql('ALTER TABLE post DROP category_id');
    }
}
