<?php
// PukiWiki - Yet another WikiWikiWeb clone.
// draft.inc.php
// Copyright 2024 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Draft management plugin (cmd=draft)

function plugin_draft_action()
{
	global $vars;

	// 読み取り専用モードではアクセス拒否
	if (PKWK_READONLY) {
		die_message('Prohibited by admin (READONLY mode)');
	}

	// 認証が有効な場合、ログインユーザーのみアクセス可能
	global $edit_auth;
	if ($edit_auth) {
		$auth_user = get_auth_user();
		if (!$auth_user) {
			die_message('Authentication required to view draft list');
		}
	}

	// Load draft library
	require_once(LIB_DIR . 'draft.php');

	$action = isset($vars['action']) ? $vars['action'] : 'list';

	switch ($action) {
		case 'delete':
			return plugin_draft_delete();
		case 'publish':
			return plugin_draft_publish();
		default:
			return plugin_draft_list();
	}
}

/**
 * Show draft list
 */
function plugin_draft_list()
{
	global $script;

	$drafts = get_draft_list();

	if (empty($drafts)) {
		$body = '<p>下書きはありません。</p>';
	} else {
		$body = '<ul>';
		foreach ($drafts as $page) {
			$time = get_draft_filetime($page);
			$time_str = format_date($time);
			$page_link = make_pagelink($page);
			$edit_link = $script . '?cmd=edit&amp;page=' . rawurlencode($page) . '&amp;load_draft=true';
			$delete_link = $script . '?cmd=draft&amp;action=delete&amp;page=' . rawurlencode($page);
			$publish_link = $script . '?cmd=draft&amp;action=publish&amp;page=' . rawurlencode($page);

			$body .= '<li>';
			$body .= $page_link;
			$body .= ' (' . $time_str . ')';
			$body .= ' [<a href="' . $edit_link . '">編集</a>]';
			$body .= ' [<a href="' . $publish_link . '" onclick="return confirm(\'この下書きを公開しますか?\')">公開</a>]';
			$body .= ' [<a href="' . $delete_link . '" onclick="return confirm(\'この下書きを削除しますか?\')">削除</a>]';
			$body .= '</li>';
		}
		$body .= '</ul>';
	}

	return array(
		'msg' => '下書き一覧',
		'body' => $body
	);
}

/**
 * Delete draft
 */
function plugin_draft_delete()
{
	global $vars, $script;

	$page = isset($vars['page']) ? $vars['page'] : '';
	if ($page === '') {
		return array(
			'msg' => 'エラー',
			'body' => '<p>ページが指定されていません。</p>'
		);
	}

	// Check permission
	check_editable($page, true, true);

	if (draft_delete($page)) {
		$body = '<p>下書きを削除しました。</p>';
		$body .= '<p><a href="' . $script . '?cmd=draft">下書き一覧に戻る</a></p>';
	} else {
		$body = '<p>下書きの削除に失敗しました。</p>';
	}

	return array(
		'msg' => '下書き削除',
		'body' => $body
	);
}

/**
 * Publish draft (convert to main page)
 */
function plugin_draft_publish()
{
	global $vars, $script;

	$page = isset($vars['page']) ? $vars['page'] : '';
	if ($page === '') {
		return array(
			'msg' => 'エラー',
			'body' => '<p>ページが指定されていません。</p>'
		);
	}

	// Check permission
	check_editable($page, true, true);

	// Get draft content
	$postdata = get_draft($page, TRUE, TRUE);
	if ($postdata === FALSE || $postdata === '') {
		return array(
			'msg' => 'エラー',
			'body' => '<p>下書きが見つかりません。</p>'
		);
	}

	// Write to main page
	page_write($page, $postdata);

	// Delete draft
	draft_delete($page);

	$page_link = make_pagelink($page);
	$body = '<p>下書きを公開しました: ' . $page_link . '</p>';
	$body .= '<p><a href="' . $script . '?cmd=draft">下書き一覧に戻る</a></p>';

	return array(
		'msg' => '下書き公開',
		'body' => $body
	);
}
