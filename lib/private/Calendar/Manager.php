<?php

declare(strict_types=1);

/**
 * @copyright 2017, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
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
namespace OC\Calendar;

use OC\AppFramework\Bootstrap\Coordinator;
use OCA\DAV\CalDAV\CalendarHome;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Calendar\Exceptions\CalendarException;
use OCP\Calendar\ICalendar;
use OCP\Calendar\ICalendarProvider;
use OCP\Calendar\ICalendarQuery;
use OCP\Calendar\ICreateFromString;
use OCP\Calendar\IManager;
use OCP\Security\ISecureRandom;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\ITip\Message;
use Sabre\VObject\Property\VCard\DateTime;
use Sabre\VObject\Reader;
use Throwable;
use function array_map;
use function array_merge;

class Manager implements IManager {

	/**
	 * @var ICalendar[] holds all registered calendars
	 */
	private $calendars = [];

	/**
	 * @var \Closure[] to call to load/register calendar providers
	 */
	private $calendarLoaders = [];

	/** @var Coordinator */
	private $coordinator;

	/** @var ContainerInterface */
	private $container;

	/** @var LoggerInterface */
	private $logger;

	private ITimeFactory $timeFactory;

	private ISecureRandom $random;

	public function __construct(Coordinator $coordinator,
								ContainerInterface $container,
								LoggerInterface $logger,
								ITimeFactory $timeFactory,
								ISecureRandom $random) {
		$this->coordinator = $coordinator;
		$this->container = $container;
		$this->logger = $logger;
		$this->timeFactory = $timeFactory;
		$this->random = $random;
	}

	/**
	 * This function is used to search and find objects within the user's calendars.
	 * In case $pattern is empty all events/journals/todos will be returned.
	 *
	 * @param string $pattern which should match within the $searchProperties
	 * @param array $searchProperties defines the properties within the query pattern should match
	 * @param array $options - optional parameters:
	 * 	['timerange' => ['start' => new DateTime(...), 'end' => new DateTime(...)]]
	 * @param integer|null $limit - limit number of search results
	 * @param integer|null $offset - offset for paging of search results
	 * @return array an array of events/journals/todos which are arrays of arrays of key-value-pairs
	 * @since 13.0.0
	 */
	public function search($pattern, array $searchProperties = [], array $options = [], $limit = null, $offset = null) {
		$this->loadCalendars();
		$result = [];
		foreach ($this->calendars as $calendar) {
			$r = $calendar->search($pattern, $searchProperties, $options, $limit, $offset);
			foreach ($r as $o) {
				$o['calendar-key'] = $calendar->getKey();
				$result[] = $o;
			}
		}

		return $result;
	}

	/**
	 * Check if calendars are available
	 *
	 * @return bool true if enabled, false if not
	 * @since 13.0.0
	 */
	public function isEnabled() {
		return !empty($this->calendars) || !empty($this->calendarLoaders);
	}

	/**
	 * Registers a calendar
	 *
	 * @param ICalendar $calendar
	 * @return void
	 * @since 13.0.0
	 */
	public function registerCalendar(ICalendar $calendar) {
		$this->calendars[$calendar->getKey()] = $calendar;
	}

	/**
	 * Unregisters a calendar
	 *
	 * @param ICalendar $calendar
	 * @return void
	 * @since 13.0.0
	 */
	public function unregisterCalendar(ICalendar $calendar) {
		unset($this->calendars[$calendar->getKey()]);
	}

	/**
	 * In order to improve lazy loading a closure can be registered which will be called in case
	 * calendars are actually requested
	 *
	 * @param \Closure $callable
	 * @return void
	 * @since 13.0.0
	 */
	public function register(\Closure $callable) {
		$this->calendarLoaders[] = $callable;
	}

	/**
	 * @return ICalendar[]
	 * @since 13.0.0
	 */
	public function getCalendars() {
		$this->loadCalendars();

		return array_values($this->calendars);
	}

	/**
	 * removes all registered calendar instances
	 * @return void
	 * @since 13.0.0
	 */
	public function clear() {
		$this->calendars = [];
		$this->calendarLoaders = [];
	}

	/**
	 * loads all calendars
	 */
	private function loadCalendars() {
		foreach ($this->calendarLoaders as $callable) {
			$callable($this);
		}
		$this->calendarLoaders = [];
	}

	/**
	 * @param string $principalUri
	 * @param array $calendarUris
	 * @return ICreateFromString[]
	 */
	public function getCalendarsForPrincipal(string $principalUri, array $calendarUris = []): array {
		$context = $this->coordinator->getRegistrationContext();
		if ($context === null) {
			return [];
		}

		return array_merge(
			...array_map(function ($registration) use ($principalUri, $calendarUris) {
				try {
					/** @var ICalendarProvider $provider */
					$provider = $this->container->get($registration->getService());
				} catch (Throwable $e) {
					$this->logger->error('Could not load calendar provider ' . $registration->getService() . ': ' . $e->getMessage(), [
						'exception' => $e,
					]);
					return [];
				}

				return $provider->getCalendars($principalUri, $calendarUris);
			}, $context->getCalendarProviders())
		);
	}

	public function getCalendarHome(string $principalUri): ?CalendarHome {
		$context = $this->coordinator->getRegistrationContext();
		if ($context === null) {
			return null;
		}

		foreach ($context->getCalendarProviders() as $registration) {
			try {
				/** @var ICalendarProvider $provider */
				$provider = $this->container->get($registration->getService());
			} catch (Throwable $e) {
				$this->logger->error('Could not load calendar provider ' . $registration->getService() . ': ' . $e->getMessage(), [
					'exception' => $e,
				]);
				continue;
			}
			return $provider->provideCalendarHome($principalUri);
		}
		return null;
	}

	public function searchForPrincipal(ICalendarQuery $query): array {
		/** @var CalendarQuery $query */
		$calendars = $this->getCalendarsForPrincipal(
			$query->getPrincipalUri(),
			$query->getCalendarUris(),
		);

		$results = [];
		foreach ($calendars as $calendar) {
			$r = $calendar->search(
				$query->getSearchPattern() ?? '',
				$query->getSearchProperties(),
				$query->getOptions(),
				$query->getLimit(),
				$query->getOffset()
			);

			foreach ($r as $o) {
				$o['calendar-key'] = $calendar->getKey();
				$results[] = $o;
			}
		}
		return $results;
	}

	public function newQuery(string $principalUri): ICalendarQuery {
		return new CalendarQuery($principalUri);
	}

	// REPLY: the attendee has to be updated in the ORGANIZER calendar
	public function handleIMipReply(string $principalUri, string $sender, string $recipient, string $calendarData): bool {
		/** @var VCalendar $vObject */
		$vObject = Reader::read($calendarData);
		/** @var VEvent $vEvent */
		$vEvent = $vObject->{'VEVENT'};

		// First, we check if the correct method is passed to us
		if (strcasecmp('REPLY', $vObject->{'METHOD'}->getValue()) !== 0) {
			$this->logger->warning('Wrong method provided for processing');
			return false;
		}

		// check if mail recipient and organizer are one and the same
		$organizer = substr($vEvent->{'ORGANIZER'}->getValue(), 7);

		if (strcasecmp($recipient, $organizer) !== 0) {
			$this->logger->warning('Recipient and ORGANIZER must be identical');
			return false;
		}

		//check if the event is in the future
		/** @var DateTime $eventTime */
		$eventTime = $vEvent->{'DTSTART'};
		if ($eventTime->getDateTime()->getTimeStamp() < $this->timeFactory->getDateTime()->getTimestamp()) { // this might cause issues with recurrences
			$this->logger->warning('Only events in the future are processed');
			return false;
		}

		$original = $this->getCalendarHome($principalUri)->searchPrincipalByUid($principalUri, $vEvent->{'UID'}->getValue());
		if (empty($original)) {
			$this->logger->info('Event not found in calendar for principal ' . $principalUri . 'and UID' . $vEvent->{'UID'}->getValue());
			return false;
		}

		$originalVObject = Reader::read($original['calendardata']);
		/** @var VEvent $originalVevent */
		$originalVevent = $originalVObject->{'VEVENT'};
		// check if the organizer in the attached calendar data is the one in the original event
		if (strcasecmp($originalVevent->{'ORGANIZER'}->getValue(), $vEvent->{'ORGANIZER'}->getValue()) !== 0) {
			$this->logger->warning('Invalid ORGANIZER passed for REPLY');
			return false;
		}

		// we need to compare the email address the REPLY is coming from (in Mail)
		// to the email address in the ATTENDEE as specified in the RFC
		$attendee = substr($vEvent->{'ATTENDEE'}->getValue(), 7);

		if (strcasecmp($sender, $attendee) !== 0) {
			$this->logger->warning('Party crashing is not supported for iMIP replies');
			return false;
		}

		if (!isset($originalVevent->ATTENDEE)) {
			$this->logger->warning('No attendees set in original VEVENT.');
			return false;
		}

		// Sabre is also doing this but is letting newly added attendees "party crash"
		// but we should not allow ATTENDEE modification here
		$found = false;
		foreach ($originalVevent->ATTENDEE as $a) {
			if (strcasecmp($a->getValue(), $vEvent->{'ATTENDEE'}->getValue()) === 0) {
				$found = true;
				break;
			}
		}
		if (!$found) {
			$this->logger->warning('Party crashing is not supported for iMIP replies');
			return false;
		}

		/** @var ICreateFromString $calendar */
		$calendar = current(array_filter($this->getCalendarsForPrincipal($principalUri), function ($calendar) use ($original) {
			return $calendar->getKey() === $original['calendarid'];
		}));

		if (!$calendar) {
			return false;
		}
		// Check if this is a writable calendar
		if (!($calendar instanceof ICreateFromString)) {
			$this->logger->error('Could not update calendar for iMIP processing as calendar' . $calendar->getUri() . 'is not writable');
			return false;
		}

		$iTipMessage = new Message();
		$iTipMessage->recipient = $vEvent->{'ORGANIZER'}->getValue();
		$iTipMessage->uid = $vEvent->{'UID'}->getValue();
		$iTipMessage->component = 'VEVENT';
		$iTipMessage->method = 'REPLY';
		$iTipMessage->sequence = $vEvent->{'SEQUENCE'}->getValue() ?? 0;
		$iTipMessage->sender = $vEvent->{'ATTENDEE'}->getValue();
		$iTipMessage->message = $vObject;
		try {
			$calendar->handleIMipMessage($iTipMessage); // sabre will handle the scheduling behind the scenes
		} catch (CalendarException $e) {
			$this->logger->error('Could not update calendar for iMIP processing', ['exception' => $e]);
			return false;
		}
		return true;
	}

	// CANCEL: the event has to be updated in the ATTENDEEs calendar
	public function handleIMipCancel(string $principalUri, string $sender, ?string $replyTo, string $recipient, string $calendarData): bool {
		$vObject = Reader::read($calendarData);
		/** @var VEvent $vEvent */
		$vEvent = $vObject->{'VEVENT'};

		// First, we check if the correct method is passed to us
		if (strcasecmp('CANCEL', $vObject->{'METHOD'}->getValue()) !== 0) {
			$this->logger->warning('Wrong method provided for processing');
			return false;
		}

		$attendee = substr($vEvent->{'ATTENDEE'}->getValue(), 7);
		if (strcasecmp($recipient, $attendee) !== 0) {
			$this->logger->warning('Recipient must be an ATTENDEE of this event');
			return false;
		}

		// Thirdly, we need to compare the email address the CANCEL is coming from (in Mail)
		// or the Reply- To Address submitted with the CANCEL email
		// to the email address in the ORGANIZER.
		// We don't want to accept a CANCEL request from just anyone
		$organizer = substr($vEvent->{'ORGANIZER'}->getValue(), 7);
		if (strcasecmp($sender, $organizer) !== 0 || strcasecmp($replyTo, $organizer) !== 0) {
			$this->logger->warning('Sender must be the ORGANIZER of this event');
			return false;
		}

		//check if the event is in the future
		/** @var DateTime $eventTime */
		$eventTime = $vEvent->{'DTSTART'};
		if ($eventTime->getDateTime()->getTimeStamp() < $this->timeFactory->getDateTime()->getTimestamp()) { // this might cause issues with recurrences
			$this->logger->warning('Only events in the future are processed');
			return false;
		}

		// Look for the original calendar the event was set in
		$original = $this->getCalendarHome($principalUri)->searchPrincipalByUid($principalUri, $vEvent->{'UID'}->getValue());
		if (empty($original)) {
			$this->logger->info('Event not found in calendar for principal ' . $principalUri . 'and UID' . $vEvent->{'UID'}->getValue());
			// this is a safe operation
			// we can ignore objects that are unavailable
			return true;
		}

		$originalVObject = Reader::read($original['calendardata']);
		/** @var VEvent $originalVevent */
		$originalVevent = $originalVObject->{'VEVENT'};
		// check if the organizer in the attached calendar data is the one in the original event
		if (strcasecmp($originalVevent->{'ORGANIZER'}->getValue(), $vEvent->{'ORGANIZER'}->getValue()) !== 0) {
			$this->logger->warning('Invalid ORGANIZER passed for CANCEL');
			return false;
		}

		// we don't need to check ATTENDEEs - sabre will ignore the event if it wasn't in the calendar to begin with

		$calendar = current(array_filter($this->getCalendarsForPrincipal($principalUri), function ($calendar) use ($original) {
			return $calendar->getKey() === $original['calendarid'];
		}));

		if (!$calendar) {
			$this->logger->error('Could not find calendar to write REPLY to');
			return false;
		}

		// Check if this is a writable calendar
		if (!($calendar instanceof ICreateFromString)) {
			$this->logger->error('Could not update calendar for iMIP processing as calendar' . $calendar->getUri() . 'is not writable');
			return false;
		}

		$iTipMessage = new Message();
		$iTipMessage->uid = $vEvent->{'UID'}->getValue();
		$iTipMessage->recipient = $vEvent->{'ATTENDEE'}->getValue();
		$iTipMessage->component = 'VEVENT';
		$iTipMessage->method = 'CANCEL';
		$iTipMessage->sequence = $vEvent->{'SEQUENCE'}->getValue() ?? 0;
		$iTipMessage->message = $vObject;
		try {
			$calendar->handleIMipMessage($iTipMessage);
			return true;
		} catch (CalendarException $e) {
			$this->logger->error('Could not update calendar for iMIP processing', ['exception' => $e]);
			return false;
		}
	}
}
