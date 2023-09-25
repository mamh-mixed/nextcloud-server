<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\UserStatus\Service;

use OC\Calendar\CalendarQuery;
use OCA\DAV\CalDAV\InvitationResponse\InvitationResponseServer;
use OCA\DAV\CalDAV\Schedule\Plugin;
use OCA\UserStatus\Db\UserStatus;
use OCA\UserStatus\Db\UserStatusMapper;
use OCA\UserStatus\Exception\InvalidClearAtException;
use OCA\UserStatus\Exception\InvalidMessageIdException;
use OCA\UserStatus\Exception\InvalidStatusIconException;
use OCA\UserStatus\Exception\InvalidStatusTypeException;
use OCA\UserStatus\Exception\StatusMessageTooLongException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\IConfig;
use OCP\IEmojiHelper;
use OCP\UserStatus\IUserStatus;
use Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp;
use Sabre\DAV\Exception\NotFound;
use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\FreeBusyGenerator;
use Sabre\VObject\Parameter;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;

/**
 * Class StatusService
 *
 * @package OCA\UserStatus\Service
 */
class StatusService {

	/** @var UserStatusMapper */
	private $mapper;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var PredefinedStatusService */
	private $predefinedStatusService;

	private IEmojiHelper $emojiHelper;

	/** @var bool */
	private $shareeEnumeration;

	/** @var bool */
	private $shareeEnumerationInGroupOnly;

	/** @var bool */
	private $shareeEnumerationPhone;

	/**
	 * List of priorities ordered by their priority
	 */
	public const PRIORITY_ORDERED_STATUSES = [
		IUserStatus::ONLINE,
		IUserStatus::AWAY,
		IUserStatus::DND,
		IUserStatus::INVISIBLE,
		IUserStatus::OFFLINE,
	];

	/**
	 * List of statuses that persist the clear-up
	 * or UserLiveStatusEvents
	 */
	public const PERSISTENT_STATUSES = [
		IUserStatus::AWAY,
		IUserStatus::DND,
		IUserStatus::INVISIBLE,
	];

	/** @var int */
	public const INVALIDATE_STATUS_THRESHOLD = 15 /* minutes */ * 60 /* seconds */;

	/** @var int */
	public const MAXIMUM_MESSAGE_LENGTH = 80;

	public function __construct(UserStatusMapper $mapper,
								ITimeFactory $timeFactory,
								PredefinedStatusService $defaultStatusService,
								IEmojiHelper $emojiHelper,
								IConfig $config) {
		$this->mapper = $mapper;
		$this->timeFactory = $timeFactory;
		$this->predefinedStatusService = $defaultStatusService;
		$this->emojiHelper = $emojiHelper;
		$this->shareeEnumeration = $config->getAppValue('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') === 'yes';
		$this->shareeEnumerationInGroupOnly = $this->shareeEnumeration && $config->getAppValue('core', 'shareapi_restrict_user_enumeration_to_group', 'no') === 'yes';
		$this->shareeEnumerationPhone = $this->shareeEnumeration && $config->getAppValue('core', 'shareapi_restrict_user_enumeration_to_phone', 'no') === 'yes';
	}

	/**
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return UserStatus[]
	 */
	public function findAll(?int $limit = null, ?int $offset = null): array {
		// Return empty array if user enumeration is disabled or limited to groups
		// TODO: find a solution that scales to get only users from common groups if user enumeration is limited to
		//       groups. See discussion at https://github.com/nextcloud/server/pull/27879#discussion_r729715936
		if (!$this->shareeEnumeration || $this->shareeEnumerationInGroupOnly || $this->shareeEnumerationPhone) {
			return [];
		}

		return array_map(function ($status) {
			return $this->processStatus($status);
		}, $this->mapper->findAll($limit, $offset));
	}

	/**
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array
	 */
	public function findAllRecentStatusChanges(?int $limit = null, ?int $offset = null): array {
		// Return empty array if user enumeration is disabled or limited to groups
		// TODO: find a solution that scales to get only users from common groups if user enumeration is limited to
		//       groups. See discussion at https://github.com/nextcloud/server/pull/27879#discussion_r729715936
		if (!$this->shareeEnumeration || $this->shareeEnumerationInGroupOnly || $this->shareeEnumerationPhone) {
			return [];
		}

		return array_map(function ($status) {
			return $this->processStatus($status);
		}, $this->mapper->findAllRecent($limit, $offset));
	}

	/**
	 * @param string $userId
	 * @return UserStatus
	 * @throws DoesNotExistException
	 */
	public function findByUserId(string $userId):UserStatus {
		$calendarStatus = $this->getCalendarAvailability($userId);
		return $this->processStatus($this->mapper->findByUserId($userId));
	}

	/**
	 * @param array $userIds
	 * @return UserStatus[]
	 */
	public function findByUserIds(array $userIds):array {
		return array_map(function ($status) {
			return $this->processStatus($status);
		}, $this->mapper->findByUserIds($userIds));
	}

	/**
	 * @param string $userId
	 * @param string $status
	 * @param int|null $statusTimestamp
	 * @param bool $isUserDefined
	 * @return UserStatus
	 * @throws InvalidStatusTypeException
	 */
	public function setStatus(string $userId,
							  string $status,
							  ?int $statusTimestamp,
							  bool $isUserDefined): UserStatus {
		try {
			$userStatus = $this->mapper->findByUserId($userId);
		} catch (DoesNotExistException $ex) {
			$userStatus = new UserStatus();
			$userStatus->setUserId($userId);
		}

		// Check if status-type is valid
		if (!\in_array($status, self::PRIORITY_ORDERED_STATUSES, true)) {
			throw new InvalidStatusTypeException('Status-type "' . $status . '" is not supported');
		}
		if ($statusTimestamp === null) {
			$statusTimestamp = $this->timeFactory->getTime();
		}

		$userStatus->setStatus($status);
		$userStatus->setStatusTimestamp($statusTimestamp);
		$userStatus->setIsUserDefined($isUserDefined);
		$userStatus->setIsBackup(false);

		if ($userStatus->getId() === null) {
			return $this->mapper->insert($userStatus);
		}

		return $this->mapper->update($userStatus);
	}

	/**
	 * @param string $userId
	 * @param string $messageId
	 * @param int|null $clearAt
	 * @return UserStatus
	 * @throws InvalidMessageIdException
	 * @throws InvalidClearAtException
	 */
	public function setPredefinedMessage(string $userId,
										 string $messageId,
										 ?int $clearAt): UserStatus {
		try {
			$userStatus = $this->mapper->findByUserId($userId);
		} catch (DoesNotExistException $ex) {
			$userStatus = new UserStatus();
			$userStatus->setUserId($userId);
			$userStatus->setStatus(IUserStatus::OFFLINE);
			$userStatus->setStatusTimestamp(0);
			$userStatus->setIsUserDefined(false);
			$userStatus->setIsBackup(false);
		}

		if (!$this->predefinedStatusService->isValidId($messageId)) {
			throw new InvalidMessageIdException('Message-Id "' . $messageId . '" is not supported');
		}

		// Check that clearAt is in the future
		if ($clearAt !== null && $clearAt < $this->timeFactory->getTime()) {
			throw new InvalidClearAtException('ClearAt is in the past');
		}

		$userStatus->setMessageId($messageId);
		$userStatus->setCustomIcon(null);
		$userStatus->setCustomMessage(null);
		$userStatus->setClearAt($clearAt);
		$userStatus->setStatusMessageTimestamp($this->timeFactory->now()->getTimestamp());

		if ($userStatus->getId() === null) {
			return $this->mapper->insert($userStatus);
		}

		return $this->mapper->update($userStatus);
	}

	/**
	 * @param string $userId
	 * @param string $status
	 * @param string $messageId
	 * @param bool $createBackup
	 * @throws InvalidStatusTypeException
	 * @throws InvalidMessageIdException
	 */
	public function setUserStatus(string $userId,
										 string $status,
										 string $messageId,
										 bool $createBackup): void {
		// Check if status-type is valid
		if (!\in_array($status, self::PRIORITY_ORDERED_STATUSES, true)) {
			throw new InvalidStatusTypeException('Status-type "' . $status . '" is not supported');
		}

		if (!$this->predefinedStatusService->isValidId($messageId)) {
			throw new InvalidMessageIdException('Message-Id "' . $messageId . '" is not supported');
		}

		if ($createBackup) {
			if ($this->backupCurrentStatus($userId) === false) {
				return; // Already a status set automatically => abort.
			}

			// If we just created the backup
			$userStatus = new UserStatus();
			$userStatus->setUserId($userId);
		} else {
			try {
				$userStatus = $this->mapper->findByUserId($userId);
			} catch (DoesNotExistException $ex) {
				$userStatus = new UserStatus();
				$userStatus->setUserId($userId);
			}
		}

		$userStatus->setStatus($status);
		$userStatus->setStatusTimestamp($this->timeFactory->getTime());
		$userStatus->setIsUserDefined(true);
		$userStatus->setIsBackup(false);
		$userStatus->setMessageId($messageId);
		$userStatus->setCustomIcon(null);
		$userStatus->setCustomMessage(null);
		$userStatus->setClearAt(null);
		$userStatus->setStatusMessageTimestamp($this->timeFactory->now()->getTimestamp());

		if ($userStatus->getId() !== null) {
			$this->mapper->update($userStatus);
			return;
		}
		$this->mapper->insert($userStatus);
	}

	/**
	 * @param string $userId
	 * @param string|null $statusIcon
	 * @param string|null $message
	 * @param int|null $clearAt
	 * @return UserStatus
	 * @throws InvalidClearAtException
	 * @throws InvalidStatusIconException
	 * @throws StatusMessageTooLongException
	 */
	public function setCustomMessage(string $userId,
									 ?string $statusIcon,
									 ?string $message,
									 ?int $clearAt): UserStatus {
		try {
			$userStatus = $this->mapper->findByUserId($userId);
		} catch (DoesNotExistException $ex) {
			$userStatus = new UserStatus();
			$userStatus->setUserId($userId);
			$userStatus->setStatus(IUserStatus::OFFLINE);
			$userStatus->setStatusTimestamp(0);
			$userStatus->setIsUserDefined(false);
		}

		// Check if statusIcon contains only one character
		if ($statusIcon !== null && !$this->emojiHelper->isValidSingleEmoji($statusIcon)) {
			throw new InvalidStatusIconException('Status-Icon is longer than one character');
		}
		// Check for maximum length of custom message
		if ($message !== null && \mb_strlen($message) > self::MAXIMUM_MESSAGE_LENGTH) {
			throw new StatusMessageTooLongException('Message is longer than supported length of ' . self::MAXIMUM_MESSAGE_LENGTH . ' characters');
		}
		// Check that clearAt is in the future
		if ($clearAt !== null && $clearAt < $this->timeFactory->getTime()) {
			throw new InvalidClearAtException('ClearAt is in the past');
		}

		$userStatus->setMessageId(null);
		$userStatus->setCustomIcon($statusIcon);
		$userStatus->setCustomMessage($message);
		$userStatus->setClearAt($clearAt);
		$userStatus->setStatusMessageTimestamp($this->timeFactory->now()->getTimestamp());

		if ($userStatus->getId() === null) {
			return $this->mapper->insert($userStatus);
		}

		return $this->mapper->update($userStatus);
	}

	/**
	 * @param string $userId
	 * @return bool
	 */
	public function clearStatus(string $userId): bool {
		try {
			$userStatus = $this->mapper->findByUserId($userId);
		} catch (DoesNotExistException $ex) {
			// if there is no status to remove, just return
			return false;
		}

		$userStatus->setStatus(IUserStatus::OFFLINE);
		$userStatus->setStatusTimestamp(0);
		$userStatus->setIsUserDefined(false);

		$this->mapper->update($userStatus);
		return true;
	}

	/**
	 * @param string $userId
	 * @return bool
	 */
	public function clearMessage(string $userId): bool {
		try {
			$userStatus = $this->mapper->findByUserId($userId);
		} catch (DoesNotExistException $ex) {
			// if there is no status to remove, just return
			return false;
		}

		$userStatus->setMessageId(null);
		$userStatus->setCustomMessage(null);
		$userStatus->setCustomIcon(null);
		$userStatus->setClearAt(null);
		$userStatus->setStatusMessageTimestamp(0);

		$this->mapper->update($userStatus);
		return true;
	}

	/**
	 * @param string $userId
	 * @return bool
	 */
	public function removeUserStatus(string $userId): bool {
		try {
			$userStatus = $this->mapper->findByUserId($userId, false);
		} catch (DoesNotExistException $ex) {
			// if there is no status to remove, just return
			return false;
		}

		$this->mapper->delete($userStatus);
		return true;
	}

	public function removeBackupUserStatus(string $userId): bool {
		try {
			$userStatus = $this->mapper->findByUserId($userId, true);
		} catch (DoesNotExistException $ex) {
			// if there is no status to remove, just return
			return false;
		}

		$this->mapper->delete($userStatus);
		return true;
	}

	/**
	 * Processes a status to check if custom message is still
	 * up to date and provides translated default status if needed
	 *
	 * @param UserStatus $status
	 * @return UserStatus
	 */
	private function processStatus(UserStatus $status): UserStatus {
		$clearAt = $status->getClearAt();

		if ($status->getStatusTimestamp() < $this->timeFactory->getTime() - self::INVALIDATE_STATUS_THRESHOLD
			&& (!$status->getIsUserDefined() || $status->getStatus() === IUserStatus::ONLINE)) {
			$this->cleanStatus($status);
		}
		if ($clearAt !== null && $clearAt < $this->timeFactory->getTime()) {
			$this->cleanStatus($status);
			$this->cleanStatusMessage($status);
		}
		if ($status->getMessageId() !== null) {
			$this->addDefaultMessage($status);
		}

		return $status;
	}

	/**
	 * @param UserStatus $status
	 */
	private function cleanStatus(UserStatus $status): void {
		if ($status->getStatus() === IUserStatus::OFFLINE && !$status->getIsUserDefined()) {
			return;
		}

		$status->setStatus(IUserStatus::OFFLINE);
		$status->setStatusTimestamp($this->timeFactory->getTime());
		$status->setIsUserDefined(false);

		$this->mapper->update($status);
	}

	/**
	 * @param UserStatus $status
	 */
	private function cleanStatusMessage(UserStatus $status): void {
		$status->setMessageId(null);
		$status->setCustomIcon(null);
		$status->setCustomMessage(null);
		$status->setClearAt(null);
		$status->setStatusMessageTimestamp(0);

		$this->mapper->update($status);
	}

	/**
	 * @param UserStatus $status
	 */
	private function addDefaultMessage(UserStatus $status): void {
		// If the message is predefined, insert the translated message and icon
		$predefinedMessage = $this->predefinedStatusService->getDefaultStatusById($status->getMessageId());
		if ($predefinedMessage !== null) {
			$status->setCustomMessage($predefinedMessage['message']);
			$status->setCustomIcon($predefinedMessage['icon']);
			$status->setStatusMessageTimestamp($this->timeFactory->now()->getTimestamp());
		}
	}

	/**
	 * @return bool false if there is already a backup. In this case abort the procedure.
	 */
	public function backupCurrentStatus(string $userId): bool {
		try {
			$this->mapper->createBackupStatus($userId);
			return true;
		} catch (Exception $ex) {
			if ($ex->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				return false;
			}
			throw $ex;
		}
	}

	public function revertUserStatus(string $userId, string $messageId, bool $revertedManually = false): ?UserStatus {
		try {
			/** @var UserStatus $userStatus */
			$backupUserStatus = $this->mapper->findByUserId($userId, true);
		} catch (DoesNotExistException $ex) {
			// No user status to revert, do nothing
			return null;
		}

		$deleted = $this->mapper->deleteCurrentStatusToRestoreBackup($userId, $messageId);
		if (!$deleted) {
			// Another status is set automatically or no status, do nothing
			return null;
		}

		if ($revertedManually && $backupUserStatus->getStatus() === IUserStatus::OFFLINE) {
			// When the user reverts the status manually they are online
			$backupUserStatus->setStatus(IUserStatus::ONLINE);
		}

		$backupUserStatus->setIsBackup(false);
		// Remove the underscore prefix added when creating the backup
		$backupUserStatus->setUserId(substr($backupUserStatus->getUserId(), 1));
		$this->mapper->update($backupUserStatus);

		return $backupUserStatus;
	}

	public function revertMultipleUserStatus(array $userIds, string $messageId): void {
		// Get all user statuses and the backups
		$findById = $userIds;
		foreach ($userIds as $userId) {
			$findById[] = '_' . $userId;
		}
		$userStatuses = $this->mapper->findByUserIds($findById);

		$backups = $restoreIds = $statuesToDelete = [];
		foreach ($userStatuses as $userStatus) {
			if (!$userStatus->getIsBackup()
				&& $userStatus->getMessageId() === $messageId) {
				$statuesToDelete[$userStatus->getUserId()] = $userStatus->getId();
			} else if ($userStatus->getIsBackup()) {
				$backups[$userStatus->getUserId()] = $userStatus->getId();
			}
		}

		// For users with both (normal and backup) delete the status when matching
		foreach ($statuesToDelete as $userId => $statusId) {
			$backupUserId = '_' . $userId;
			if (isset($backups[$backupUserId])) {
				$restoreIds[] = $backups[$backupUserId];
			}
		}

		$this->mapper->deleteByIds(array_values($statuesToDelete));

		// For users that matched restore the previous status
		$this->mapper->restoreBackupStatuses($restoreIds);
	}

	public function getFreebusy($userId, $vavilability) {
		$user = $this->userManager->get($userId);
		$email = $user->getEMailAddress();
		$server = new InvitationResponseServer();
		$server = $server->getServer();

		/** @var \OCA\DAV\CalDAV\Schedule\Plugin $schedulingPlugin */
		$schedulingPlugin = $server->getPlugin('caldav-schedule');
		$caldavNS = '{'.$schedulingPlugin::NS_CALDAV.'}';

		/** @var \Sabre\DAVACL\Plugin $aclPlugin */
		$aclPlugin = $server->getPlugin('acl');
		if ('mailto:' === substr($email, 0, 7)) {
			$email = substr($email, 7);
		}

		$result = $aclPlugin->principalSearch(
			['{http://sabredav.org/ns}email-address' => $email],
			[
				'{DAV:}principal-URL',
				$caldavNS.'calendar-home-set',
				$caldavNS.'schedule-inbox-URL',
				'{http://sabredav.org/ns}email-address',
			]
		);

		if (!count($result) || !isset($result[0][200][$caldavNS.'schedule-inbox-URL'])) {
			throw new NotFound();
		}

		$inboxUrl = $result[0][200][$caldavNS.'schedule-inbox-URL']->getHref();

		// Do we have permission?
		$aclPlugin->checkPrivileges($inboxUrl, $caldavNS.'schedule-query-freebusy');


		$uris = [];
		$calendarTimeZone = new DateTimeZone('UTC');

		$calendars = $this->calendarManager->getCalendarsForPrincipal('principals/users/' . $userId);
		$query = new CalendarQuery('principals/users/' . $userId);

		foreach ($calendars as $calendarObjects) {
			if (!$calendarObjects instanceof \OCP\Calendar\ICalendar) {
				continue;
			}

			$sct = $calendarObjects->getSchedulingTransparency();

			if (!empty($sct) && ScheduleCalendarTransp::TRANSPARENT == $sct->getValue()) {
				// If a calendar is marked as 'transparent', it means we must
				// ignore it for free-busy purposes.
				continue;
			}

			$ctz = $calendarObjects->getSchedulingTimezone();
			if (!empty($ctz)) {
				$vtimezoneObj = Reader::read($ctz);
				$calendarTimeZone = $vtimezoneObj->VTIMEZONE->getTimeZone();

				// Destroy circular references so PHP can garbage collect the object.
				$vtimezoneObj->destroy();
			}
			$query->addSearchCalendar($calendarObjects->getUri());
		}
		$dtStart = new \DateTimeImmutable();
		$dtEnd = new \DateTimeImmutable('+24 hours');
		$query->setTimerangeStart($dtStart);
		$query->setTimerangeEnd($dtEnd);
		$results = $this->calendarManager->searchForPrincipal($query);

		$calendarObjects = new VCalendar();
		foreach ($results as $objectInfo) {
			$vEvent = new VEvent($calendarObjects, 'VEVENT');
			foreach($objectInfo['objects'] as $component) {
				foreach ($component as $key =>  $value) {
					$vEvent->add($key, $value[0]);
				}
			}
			$calendarObjects->add($vEvent);
		}

		$vcalendar = new VCalendar();
		$vcalendar->METHOD = 'REQUEST';

		$generator = new FreeBusyGenerator();
		$generator->setObjects($calendarObjects);
		$generator->setTimeRange($dtStart, $dtEnd);
		$generator->setBaseObject($vcalendar);
		$generator->setTimeZone($calendarTimeZone);

		if (!empty($vavilability)) {
			$generator->setVAvailability(
				Reader::read(
					$vavilability
				)
			);
		}
		$result = $generator->getResult();

		// We have the intersection of VAVILABILITY and all VEVENTS in all calendars now
		// We only need to handle the first result.

		if (!isset($result->VFREEBUSY)) {
			return ['status' => IUserStatus::ONLINE, $this->time->getDateTime('+1hour')->getTimestamp()];
		}

		/** @var Component $freeBusyComponent */
		$freeBusyComponent = $result->VFREEBUSY;
		$freeBusyProperties = $freeBusyComponent->select('FREEBUSY');
		// If there is no Free-busy property at all, the time-range is empty and available
		if (count($freeBusyProperties) === 0) {
			return ['FREE', $this->time->getDateTime('+1hour')->getTimestamp()];
		}

		// If more than one Free-Busy property was returned, it means that an event
		// starts or ends inside this time-range, so it's not available and we return false
		if (count($freeBusyProperties) > 1) {
			return ['BUSY', $dtEnd->getTimestamp()];
		}

		/** @var Property $freeBusyProperty */
		$freeBusyProperty = $freeBusyProperties[0];
		if (!$freeBusyProperty->offsetExists('FBTYPE')) {
			// If there is no FBTYPE, it means it's busy
			return false;
		}

		$fbTypeParameter = $freeBusyProperty->offsetGet('FBTYPE');
		if (!($fbTypeParameter instanceof Parameter)) {
			return false;
		}

		return (strcasecmp($fbTypeParameter->getValue(), 'FREE') === 0);

	}

	private function getCalendarAvailability(string $userId) {
		$user = $this->userManager->get($userId);
		$email = $user->getEMailAddress();
		$server = new InvitationResponseServer();
		$server = $server->getServer();

		/** @var \OCA\DAV\CalDAV\Schedule\Plugin $schedulingPlugin */
		$schedulingPlugin = $server->getPlugin('caldav-schedule');
		$caldavNS = '{'.$schedulingPlugin::NS_CALDAV.'}';

		/** @var \Sabre\DAVACL\Plugin $aclPlugin */
		$aclPlugin = $server->getPlugin('acl');
		if ('mailto:' === substr($email, 0, 7)) {
			$email = substr($email, 7);
		}

		$result = $aclPlugin->principalSearch(
			['{http://sabredav.org/ns}email-address' => $email],
			[
				'{DAV:}principal-URL',
				$caldavNS.'calendar-home-set',
				$caldavNS.'schedule-inbox-URL',
				'{http://sabredav.org/ns}email-address',
			]
		);

		if (!count($result) || !isset($result[0][200][$caldavNS.'schedule-inbox-URL'])) {
			throw new NotFound();
		}

		$inboxUrl = $result[0][200][$caldavNS.'schedule-inbox-URL']->getHref();

		// Do we have permission?
		$aclPlugin->checkPrivileges($inboxUrl, $caldavNS.'schedule-query-freebusy');
		$calendarTimeZone = new DateTimeZone('UTC');

		$calendars = $this->calendarManager->getCalendarsForPrincipal('principals/users/' . $userId);
		$query = new CalendarQuery('principals/users/' . $userId);

		foreach ($calendars as $calendarObjects) {
			if (!$calendarObjects instanceof \OCP\Calendar\ICalendar) {
				continue;
			}

			$sct = $calendarObjects->getSchedulingTransparency();

			if (!empty($sct) && ScheduleCalendarTransp::TRANSPARENT == $sct->getValue()) {
				// If a calendar is marked as 'transparent', it means we must
				// ignore it for free-busy purposes.
				continue;
			}

			$ctz = $calendarObjects->getSchedulingTimezone();
			if (!empty($ctz)) {
				$vtimezoneObj = Reader::read($ctz);
				$calendarTimeZone = $vtimezoneObj->VTIMEZONE->getTimeZone();

				// Destroy circular references so PHP can garbage collect the object.
				$vtimezoneObj->destroy();
			}
			$query->addSearchCalendar($calendarObjects->getUri());
		}
		$dtStart = new \DateTimeImmutable();
		$dtEnd = new \DateTimeImmutable('+24 hours');
		$query->setTimerangeStart($dtStart);
		$query->setTimerangeEnd($dtEnd);
		$results = $this->calendarManager->searchForPrincipal($query);

		$calendarObjects = new VCalendar();
		foreach ($results as $objectInfo) {
			$vEvent = new VEvent($calendarObjects, 'VEVENT');
			foreach($objectInfo['objects'] as $component) {
				foreach ($component as $key =>  $value) {
					$vEvent->add($key, $value[0]);
				}
			}
			$calendarObjects->add($vEvent);
		}

		$vcalendar = new VCalendar();
		$vcalendar->METHOD = 'REQUEST';

		$generator = new FreeBusyGenerator();
		$generator->setObjects($calendarObjects);
		$generator->setTimeRange($dtStart, $dtEnd);
		$generator->setBaseObject($vcalendar);
		$generator->setTimeZone($calendarTimeZone);

		$vavilability = $this->getAvailabilityFromPropertiesTable($userId);
		if (!empty($vavilability)) {
			$generator->setVAvailability(
				Reader::read(
					$vavilability
				)
			);
		}
		$result = $generator->getResult();

		// We have the intersection of VAVILABILITY and all VEVENTS in all calendars now
		// We only need to handle the first result.

		if (!isset($result->VFREEBUSY)) {
			return ['status' => IUserStatus::ONLINE, $this->time->getDateTime('+1hour')->getTimestamp()];
		}

		/** @var Component $freeBusyComponent */
		$freeBusyComponent = $result->VFREEBUSY;
		$freeBusyProperties = $freeBusyComponent->select('FREEBUSY');
		// If there is no Free-busy property at all, the time-range is empty and available
		if (count($freeBusyProperties) === 0) {
			return ['FREE', $this->time->getDateTime('+1hour')->getTimestamp()];
		}

		// If more than one Free-Busy property was returned, it means that an event
		// starts or ends inside this time-range, so it's not available and we return false
		if (count($freeBusyProperties) > 1) {
			return ['BUSY', $dtEnd->getTimestamp()];
		}

		/** @var Property $freeBusyProperty */
		$freeBusyProperty = $freeBusyProperties[0];
		if (!$freeBusyProperty->offsetExists('FBTYPE')) {
			// If there is no FBTYPE, it means it's busy
			return false;
		}

		$fbTypeParameter = $freeBusyProperty->offsetGet('FBTYPE');
		if (!($fbTypeParameter instanceof Parameter)) {
			return false;
		}

		return (strcasecmp($fbTypeParameter->getValue(), 'FREE') === 0);
	}

	/**
	 * @param string $userId
	 * @return false|string
	 */
	protected function getAvailabilityFromPropertiesTable(string $userId) {
		$propertyPath = 'calendars/' . $userId . '/inbox';
		$propertyName = '{' . Plugin::NS_CALDAV . '}calendar-availability';

		$query = $this->connection->getQueryBuilder();
		$query->select('propertyvalue')
			->from('properties')
			->where($query->expr()->eq('userid', $query->createNamedParameter($userId)))
			->andWhere($query->expr()->eq('propertypath', $query->createNamedParameter($propertyPath)))
			->andWhere($query->expr()->eq('propertyname', $query->createNamedParameter($propertyName)))
			->setMaxResults(1);

		$result = $query->executeQuery();
		$property = $result->fetchOne();
		$result->closeCursor();

		return $property;
	}

}
