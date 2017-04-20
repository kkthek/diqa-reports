<?php
namespace DIQA\Reports\Utils;

use DIQA\Util\LoggerUtils;
class ProgressUtils {

	/**
	 * Add a new satus to an existing job progress, newline-separeated.
	 *
	 * @param int $user_id
	 * @param string $job_key
	 * @param string $job_category
	 * @param int $status (how many steps of $total steps did we do yet)
	 * @param int $total
	 * @param string $status_text Optional text
	 */
	public static function addJobProgress($user_id, $job_key, $job_category, $status, $total, $status_text = 'Start') {
		$db =& wfGetDB( DB_MASTER );

		$res = $db->query(sprintf('SELECT id FROM diqareports_job_progress '.
				'WHERE job_key = %s AND job_category = %s',
			static::quote($job_key), static::quote($job_category)));

		if ($db->numRows( $res ) > 0) {
			// update
			$db->query(sprintf(
					'UPDATE diqareports_job_progress'.
					' SET status = %s, total = %s, status_text = CONCAT(status_text, "\n", %s)'.
					' WHERE job_key = %s AND job_category = %s',
					$status,
					$total,
					static::quote($status_text),
					static::quote($job_key),
					static::quote($job_category)));

		} else {
			// insert new
			$db->query(sprintf(
					'INSERT INTO diqareports_job_progress'.
					' (user_id, job_key, job_category, started_at, status, total, status_text)'.
					' VALUES (%s, %s, %s, %s, %s, %s, %s)',
					$user_id,
					static::quote($job_key),
					static::quote($job_category),
					static::quote(date("Y-m-d H:i:s")),
					$status,
					$total,
					static::quote($status_text)));
		}
	}

	/**
	 * Set a new job progress or updates an old with the given status-text.
	 *
	 * @param int $user_id
	 * @param string $job_key
	 * @param string $job_category
	 * @param int $status (how many steps of $total steps did we do yet)
	 * @param int $total
	 * @param string $status_text Optional text
	 */
	public static function setJobProgress($user_id, $job_key, $job_category, $status, $total, $status_text = 'Start') {
		$db =& wfGetDB( DB_MASTER );

		$res = $db->query(sprintf('SELECT id FROM diqareports_job_progress '.
				'WHERE job_key = %s AND job_category = %s',
				static::quote($job_key), static::quote($job_category)));

		if ($db->numRows( $res ) > 0) {
			// update
			$db->query(sprintf(
					'UPDATE diqareports_job_progress'.
					' SET status = %s, total = %s, status_text = %s'.
					' WHERE job_key = %s AND job_category = %s',
					$status,
					$total,
					static::quote($status_text),
					static::quote($job_key),
					static::quote($job_category)));

		} else {
			// insert new
			$db->query(sprintf(
					'INSERT INTO diqareports_job_progress'.
					' (user_id, job_key, job_category, started_at, status, total, status_text)'.
					' VALUES (%s, %s, %s, %s, %s, %s, %s)',
					$user_id,
					static::quote($job_key),
					static::quote($job_category),
					static::quote(date("Y-m-d H:i:s")),
					$status,
					$total,
					static::quote($status_text)));
		}
	}

	/**
	 * Set a new job progress or updates an old one with the given status-text.
	 * Automatically increases the value of the current status by 1.
	 * Does the read and update in ONE transaction.
	 *
	 * @param int $user_id
	 * @param string $job_key
	 * @param string $job_category
	 * @param int $total
	 * @param string $status_text Optional text
	 * @return array with
	 *		int    status (how many steps of $total steps did we do yet)
	 * 		int    total
 	 * 		string statusText
 	 * 		string job_key
	 * 		string job_category
	 * 		or -1 if this job does not exist
	 */
	public static function updateJobProgress($user_id, $job_key, $job_category, $status_text = 'Start') {
		$db =& wfGetDB( DB_MASTER );

		$db->begin();

		$total = static::getRealTotal($job_category);

		$res = $db->query(sprintf('SELECT id FROM diqareports_job_progress' .
				' WHERE job_key = %s AND job_category = %s',
				static::quote($job_key), static::quote($job_category)));

		if ($db->numRows( $res ) > 0) {
			// update
			$db->query(sprintf(
					'UPDATE diqareports_job_progress' .
					' SET status = status + 1, total = status + %s, status_text = concat(%s, status, "/", status + %s)' .
					' WHERE job_key = %s AND job_category = %s',
					$total,
					static::quote($status_text),
					$total,
					static::quote($job_key),
					static::quote($job_category)));

		} else {
			// insert new
			$db->query(sprintf(
					'INSERT INTO diqareports_job_progress' .
					' (user_id, job_key, job_category, started_at, status, total, status_text)'.
					' VALUES (%s, %s, %s, %s, %s, %s, concat(%s, "1/", %s)',
					$user_id,
					static::quote($job_key),
					static::quote($job_category),
					static::quote(date("Y-m-d H:i:s")),
					1,
					$total,
					static::quote($status_text),
					$total));
		}

		$db->commit();

		$progress = static::getJobProgress($job_key, $job_category);
		if($progress == -1){
			return -1;
		} else {
			return $progress[0];
		}
	}

	/**
	 * calculates the real number of pending jobs
	 *
	 * @param string $jobCategory the job category (string or class-name) for which to find the remaining open jobs
	 * @return int
	 */
	public static function getRealTotal($jobCategory) {

		$classpath = explode('\\', $jobCategory);
		// if it is a classpath we only want the last element, i.e. the classname (without namespace)
		$jobCategory = $classpath[count($classpath)-1];

		$group = \JobQueueGroup::singleton();
		$jobQueue= $group->get($jobCategory);
		if($jobQueue) {
			$count = $jobQueue->getSize();
		} else {
			$count = 0;
		}

		$logger = new LoggerUtils("ProgressUtils", "ODBpendenzensammler");
		$logger->debug("Pending Jobs ($jobCategory): \t$count");

		return $count;
	}


	/**
	 * create a new job progress (inkl. remove a matching old one)
	 *
	 * @param int $user_id
	 * @param string $job_key
	 * @param string $job_category
	 */
	public static function createJobProgress($user_id, $job_key, $job_category) {
		static::removeJobProgress($job_key, $job_category);
		static::setJobProgress($user_id, $job_key, $job_category, 1, 100, 'Start');
	}

	/**
	 * Removes the progress from the table for the given job key and category
	 *
	 * @param string $job_key
	 * @param string $job_category
	 */
	public static function removeJobProgress($job_key = null, $job_category = '%') {
		$db =& wfGetDB( DB_MASTER );

		if($job_key != null) {
			$res = $db->query('DELETE FROM diqareports_job_progress WHERE job_key LIKE ' . static::quoteForLike($job_key) .
					' AND job_category LIKE ' . static::quoteForLike($job_category));
		} else {
			$res = $db->query('DELETE FROM diqareports_job_progress WHERE job_category LIKE ' . static::quoteForLike($job_category));
		}
	}

	/**
	 * Removes the progress from the table for the given job key and category
	 *
	 * @param string $job_key
	 * @param string $job_category
	 */
	public static function removeAllFinishedJobs() {
		$db =& wfGetDB( DB_MASTER );

		$db->query(sprintf('DELETE FROM diqareports_job_progress WHERE status >= total'));
	}

	/**
	 * Gets a job progress
	 *
	 * @param string $job_key can be '%' to find all jobKeys
	 * @param string $job_category
	 * @param int $user_id
	 * @return array of arrays with
	 *		int    status (how many steps of $total steps did we do yet)
	 * 		int    total
 	 * 		string statusText
 	 * 		string job_key
	 * 		string job_category
	 * 		or -1 if this job does not exist
	 */
	public static function getJobProgress($job_key = '%', $job_category = '%', $user_id = null) {
		$db =& wfGetDB( DB_MASTER );

		$queryString = 'SELECT status, total, status_text, job_key, job_category'.
				' FROM diqareports_job_progress'.
				' WHERE job_category LIKE ' . static::quoteForLike($job_category).
		        ' AND job_key LIKE ' . static::quoteForLike($job_key);

		if($user_id != null) {
			$queryString .= " AND user_id = $user_id";
		}

		$queryString .= " ORDER BY job_key, job_category";

		$res = $db->query($queryString);
		if ($db->numRows( $res ) > 0) {
			$y = array();

			while($row = $db->fetchRow( $res )) {
				$y[] = array(
						'status' => $row['status'],
						'total' => $row['total'],
						'status_text' => $row['status_text'],
						'job_key' => $row['job_key'],
						'job_category' => $row['job_category']
				);
			}

			return $y;
		}
		return -1;
	}

	private static function quote($str) {
		$db =& wfGetDB( DB_MASTER );
		return $db->addQuotes($str);
	}

	private static function quoteForLike($str) {
		return str_replace('\\', '\\\\', static::quote($str));
	}

}