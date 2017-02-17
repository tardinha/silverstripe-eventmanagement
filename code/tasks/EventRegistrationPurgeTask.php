<?php
/**
 * A task to remove unconfirmed event registrations that are older than the
 * cutoff date to free up the places.
 *
 * @package silverstripe-eventmanagement
 */
class EventRegistrationPurgeTask extends BuildTask {

	public function getTitle() {
		return 'Event Registration Purge Task';
	}

	public function getDescription() {
		return 'Cancels unconfirmed and unsubmitted registrations older than '
			.  'the cut-off date to free up the places.';
	}

	public function run($request) {
		$this->purgeUnsubmittedRegistrations();
		$this->purgeUnconfirmedRegistrations();
	}

	protected function purgeUnsubmittedRegistrations() {

		$items = EventRegistration::get()
								->filter("Status", "Unsubmitted")
								->where("\"EventRegistration\".\"Created\" + INTERVAL \"RegistrableEvent\".\"RegistrationTimeLimit\" SECOND < '" . Convert::raw2sql(SS_DateTime::now()) . "'")
								->innerJoin("CalendarDateTime", "\"CalendarDateTime\".\"ID\" = \"EventRegistration\".\"TimeID\"")
								->innerJoin("CalendarEvent", "\"CalendarEvent\".\"ID\" = \"CalendarDateTime\".\"EventID\"")
								->innerJoin("RegistrableEvent", "\"RegistrableEvent\".\"ID\" = \"CalendarEvent\".\"ID\"");

		if ($items) {
			$count = count($items);

			foreach ($items as $registration) {
				$registration->delete();
			}
		} else {
			$count = 0;
		}

		echo "$count unsubmitted registrations were permantently deleted.\n";
	}

	protected function purgeUnconfirmedRegistrations() {
		$query = new SQLQuery();
		$conn    = DB::getConn();

		$query->select('"EventRegistration"."ID"');
		$query->from('"EventRegistration"');

		$query->innerJoin('CalendarDateTime', '"TimeID" = "DateTime"."ID"', 'DateTime');
		$query->innerJoin('CalendarEvent', '"DateTime"."EventID" = "Event"."ID"', 'Event');
		$query->innerJoin('RegistrableEvent', '"Event"."ID" = "Registrable"."ID"', 'Registrable');

		$query->where('"Registrable"."ConfirmTimeLimit" > 0');
		$query->where('"Status"', 'Unconfirmed');

		$created = $conn->formattedDatetimeClause('"EventRegistration"."Created"', '%U');
		$query->where(sprintf(
			'%s < %s', $created . ' + "Registrable"."ConfirmTimeLimit"', time()
		));

		if ($ids = $query->execute()->column()) {
			$count = count($ids);

			DB::query(sprintf(
				'UPDATE "EventRegistration" SET "Status" = \'Canceled\' WHERE "ID" IN (%s)',
				implode(', ', $ids)
			));
		} else {
			$count = 0;
		}

		echo "$count unconfirmed registrations were canceled.\n";
	}

}
