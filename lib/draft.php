<?php
// PukiWiki - Yet another WikiWikiWeb clone.
// draft.php
// Copyright
//   2024 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Draft related functions

/**
 * Check if draft exists for the page
 *
 * @param $page page name
 * @return bool true if draft exists
 */
function has_draft($page)
{
	$file = get_draft_filename($page);
	return file_exists($file);
}

/**
 * Get draft source data of the page
 *
 * @param $page page name
 * @param $lock lock
 * @param $join true: return string, false: return array of string
 * @return mixed draft data or FALSE if error occurred
 */
function get_draft($page, $lock = TRUE, $join = FALSE)
{
	$result = $join ? '' : array();
	$file = get_draft_filename($page);

	if (file_exists($file)) {
		if ($lock) {
			$fp = @fopen($file, 'r');
			if ($fp === FALSE) return FALSE;
			flock($fp, LOCK_SH);
		}

		if ($join) {
			// Returns a value
			$size = filesize($file);
			if ($size === FALSE) {
				$result = FALSE;
			} else if ($size == 0) {
				$result = '';
			} else {
				$result = fread($fp, $size);
				if ($result !== FALSE) {
					// Remove metadata line
					$lines = explode("\n", $result);
					if (isset($lines[0]) && strpos($lines[0], '#draft_saved:') === 0) {
						array_shift($lines);
					}
					$result = implode("\n", $lines);
					// Removing Carriage-Return
					$result = str_replace("\r", '', $result);
				}
			}
		} else {
			// Returns an array
			$result = file($file);
			if ($result !== FALSE) {
				// Remove metadata line
				if (isset($result[0]) && strpos($result[0], '#draft_saved:') === 0) {
					array_shift($result);
				}
				// Removing Carriage-Return
				$result = str_replace("\r", '', $result);
			}
		}

		if ($lock) {
			flock($fp, LOCK_UN);
			@fclose($fp);
		}
	}

	return $result;
}

/**
 * Get draft physical file name
 *
 * @param $page page name
 * @return string draft file path
 */
function get_draft_filename($page)
{
	return DRAFT_DIR . encode($page) . '.txt';
}

/**
 * Get draft last-modified filetime
 *
 * @param $page page name
 * @return int filetime or 0 if not exists
 */
function get_draft_filetime($page)
{
	return has_draft($page) ? filemtime(get_draft_filename($page)) - LOCALZONE : 0;
}

/**
 * Save draft data
 *
 * @param $page page name
 * @param $postdata draft data
 * @return bool success
 */
function draft_write($page, $postdata)
{
	if (PKWK_READONLY) return FALSE;

	$page = strip_bracket($page);
	$file = get_draft_filename($page);

	// Add metadata
	$metadata = '#draft_saved:' . get_date_atom(UTIME) . "\n";
	$content = $metadata . $postdata;

	// Write to file
	$fp = fopen($file, 'w');
	if ($fp === FALSE) return FALSE;

	flock($fp, LOCK_EX);
	rewind($fp);
	fputs($fp, $content);
	flock($fp, LOCK_UN);
	fclose($fp);

	return TRUE;
}

/**
 * Delete draft data
 *
 * @param $page page name
 * @return bool success
 */
function draft_delete($page)
{
	if (PKWK_READONLY) return FALSE;

	$file = get_draft_filename($page);
	if (file_exists($file)) {
		return unlink($file);
	}
	return TRUE;
}

/**
 * Get list of all draft pages
 *
 * @return array list of page names that have drafts
 */
function get_draft_list()
{
	$pages = array();

	if ($dh = opendir(DRAFT_DIR)) {
		while (($file = readdir($dh)) !== FALSE) {
			if (preg_match('/^(.+)\.txt$/', $file, $matches)) {
				$page = decode($matches[1]);
				$pages[] = $page;
			}
		}
		closedir($dh);
	}

	// Sort by modification time (newest first)
	usort($pages, function($a, $b) {
		return get_draft_filetime($b) - get_draft_filetime($a);
	});

	return $pages;
}
