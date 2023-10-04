<?php

declare(strict_types=1);

namespace OC\FilesMetadata;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OC\FilesMetadata\Event\FilesMetadataEvent;
use OC\FilesMetadata\Model\FilesMetadata;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\FilesMetadata\Exceptions\FilesMetadataNotFoundException;
use OCP\FilesMetadata\IFilesMetadata;
use OCP\FilesMetadata\IFilesMetadataManager;
use OCP\FilesMetadata\IFilesMetadataQueryHelper;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class FilesMetadataManager implements IFilesMetadataManager {
	public const TABLE_METADATA = 'files_metadata';
	public const TABLE_METADATA_INDEX = 'files_metadata_index';

	public function __construct(
		private IDBConnection $dbConnection,
		private IEventDispatcher $eventDispatcher,
		private FilesMetadataQueryHelper $filesMetadataQueryHelper,
		private LoggerInterface $logger
	) {
	}

	public function refreshMetadata(
		int $fileId,
		bool $asBackgroundJob = false,
		bool $fromScratch = false
	): IFilesMetadata {
		$metadata = null;
		if (!$fromScratch) {
			try {
				$metadata = $this->selectMetadata($fileId);
			} catch (FilesMetadataNotFoundException $e) {
			}
		}

		if (is_null($metadata)) {
			$metadata = new FilesMetadata($fileId);
		}

		$event = new FilesMetadataEvent($fileId, $metadata);
		$this->eventDispatcher->dispatchTyped($event);
		$this->saveMetadata($event->getMetadata());

		return $metadata;
	}

	/**
	 * @param int $fileId
	 * @param bool $createIfNeeded
	 *
	 * @return IFilesMetadata
	 * @throws FilesMetadataNotFoundException
	 */
	public function getMetadata(int $fileId, bool $createIfNeeded = false): IFilesMetadata {
		try {
			return $this->selectMetadata($fileId);
		} catch (FilesMetadataNotFoundException $e) {
			if ($createIfNeeded) {
				return $this->refreshMetadata($fileId);
			}

			throw $e;
		}
	}


	public function saveMetadata(IFilesMetadata $filesMetadata): void {
		if ($filesMetadata->getFileId() === 0 || !$filesMetadata->updated()) {
			return;
		}

		try {
			try {
				$this->insertMetadata($filesMetadata);
			} catch (UniqueConstraintViolationException $e) {
				$this->updateMetadata($filesMetadata);
			}
		} catch (Exception $e) {
			$this->logger->warning('exception while saveMetadata()', ['exception' => $e]);

			return;
		}

//		$this->removeDeprecatedMetadata($filesMetadata);
		// remove indexes from metadata_index that are not in the list of indexes anymore.
		foreach ($filesMetadata->listIndexes() as $index) {
			// foreach index, update entry in table metadata_index
			// if no update, insert as new row
			// !! we might want to get the type of the value to be indexed at one point !!
		}
	}

	private function removeDeprecatedMetadata(IFilesMetadata $filesMetadata): void {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->delete(self::TABLE_METADATA_INDEX)
		   ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($filesMetadata->getFileId(), IQueryBuilder::PARAM_INT)))
		   ->andWhere($qb->expr()->notIn('file_id', $filesMetadata->listIndexes(), IQueryBuilder::PARAM_STR_ARRAY));
		$qb->executeStatement();
	}


	public function getQueryHelper(): IFilesMetadataQueryHelper {
		return $this->filesMetadataQueryHelper;
	}

	private function insertMetadata(IFilesMetadata $filesMetadata): void {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->insert(self::TABLE_METADATA)
		   ->setValue('file_id', $qb->createNamedParameter($filesMetadata->getFileId(), IQueryBuilder::PARAM_INT))
		   ->setValue('json', $qb->createNamedParameter(json_encode($filesMetadata->jsonSerialize())))
		   ->setValue('sync_token', $qb->createNamedParameter('abc'))
		   ->setValue('last_update', $qb->createFunction('NOW()'));
		$qb->execute();
	}

	/**
	 * @param IFilesMetadata $filesMetadata
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function updateMetadata(IFilesMetadata $filesMetadata): void {
		// TODO check sync_token on update
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update(self::TABLE_METADATA)
		   ->set('json', $qb->createNamedParameter(json_encode($filesMetadata->jsonSerialize())))
		   ->set('sync_token', $qb->createNamedParameter('abc'))
		   ->set('last_update', $qb->createFunction('NOW()'))
		   ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($filesMetadata->getFileId(), IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * @param int $fileId
	 *
	 * @return IFilesMetadata
	 * @throws FilesMetadataNotFoundException
	 */
	private function selectMetadata(int $fileId): IFilesMetadata {
		try {
			$qb = $this->dbConnection->getQueryBuilder();
			$qb->select('json')->from(self::TABLE_METADATA);
			$qb->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
			$result = $qb->executeQuery();
			$data = $result->fetch();
			$result->closeCursor();
		} catch (Exception $e) {
			$this->logger->warning('exception while getMetadataFromDatabase()', ['exception' => $e, 'fileId' => $fileId]);
			throw new FilesMetadataNotFoundException();
		}

		if ($data === false) {
			throw new FilesMetadataNotFoundException();
		}

		$metadata = new FilesMetadata($fileId);
		$metadata->import($data['json'] ?? '');

		return $metadata;
	}
}
