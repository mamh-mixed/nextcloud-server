<?php

declare(strict_types=1);

namespace OC\Core\Migrations;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version28000Date20231004103301 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('files_metadata')) {
			$table = $schema->createTable('files_metadata');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 15,
				'unsigned' => true,
			]);
			$table->addColumn('file_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 15,
			]);
			$table->addColumn('json', Types::TEXT);
			$table->addColumn('sync_token', Types::STRING, [
				'length' => 15,
			]);
			$table->addColumn('last_update', Types::DATETIME);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['file_id'], 'files_meta_fileid');
		}

		if (!$schema->hasTable('files_metadata_index')) {
			$table = $schema->createTable('files_metadata_index');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 15,
				'unsigned' => true,
			]);
			$table->addColumn('file_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 15,
			]);
			$table->addColumn('k', Types::STRING, [
				'notnull' => false,
				'length' => 31,
			]);
			$table->addColumn('v', Types::STRING, [
				'notnull' => false,
				'length' => 63,
			]);
			$table->addColumn('v_int', Types::BIGINT, [
				'notnull' => false,
				'length' => 11,
			]);
			$table->addColumn('last_update', Types::DATETIME);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['k', 'v', 'v_int'], 'files_meta_indexes');
		}

		return $schema;
	}
}
