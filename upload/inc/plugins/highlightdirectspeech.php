<?php
/**
 * Der Code und die Idee (vor allem das Javascript) basiert auf Amaryllions 'Highlight Direct Speech'
 * https://www.mybb.de/erweiterungen/18x/plugins-themenanzeige/highlight-direct-speech/
 **/ 

if (!defined("IN_MYBB")) {
	die("This file cannot be accessed directly.");
}

// error_reporting(E_ALL);
// echo ini_get('display_errors');


function highlightdirectspeech_info()
{
	global $lang;

	return array(
		'name'			=> 'Wörtliche Rede hervorheben',
		'description'	=> 'Dieses Plugin ermöglicht es wörtliche Rede in Posts hervorzuheben. Entweder im Showthread automatisch, per Button, oder gar nicht. Dabei kann der Nutzer seine Präferenz im UserCP einstellen. Zusätzlich kann vom Admin aktiviert werden, dass schon beim Antoworten/Thread erstellen ein Tag der Wahl (z.B. &lt;b&gt; oder auch &lt;span style="..."&gt;) um die wörtliche Rede gesetzt wird.',
		'website'		=> 'https://github.com/katjalennartz/highlightdirectspeech',
		'author'		=> 'Risuena',
		'authorsite'	=> 'https://github.com/katjalennartz',
		'version'		=> '1.0',
		'compatibility'	=> '18*',
	);
}
function highlightdirectspeech_is_installed()
{
	global $db;
	if ($db->field_exists("directspeach", "users")) {
		return true;
	}
	return false;
}

function highlightdirectspeech_install()
{
	global $db;
	$db->add_column("users", "directspeach", "INT(1) NOT NULL DEFAULT 0");

	highlightdirectspeech_add_templates();
	highlightdirectspeech_add_settings("install");
}

function highlightdirectspeech_add_settings($type = "install")
{
	global $db;
	if ($type == "install") {
		//Einstellungs Gruppe anlegen
		$setting_group = array(
			'name' => 'highlightdirectspeech',
			'title' => 'Wörtliche Rede hervorheben',
			'description' => 'Einstellungen für das Hervorheben von wörtlicher Rede in Posts',
			'disporder' => 6, // The order your setting group will display
			'isdefault' => 0
		);
		$gid = $db->insert_query("settinggroups", $setting_group);
	} else {
		//update, keine Installation, Gruppe ist also schonv vorhanden
		$gid = $db->fetch_field($db->simple_select("settinggroups", "gid", "name = 'highlightdirectspeech'"), "gid");
	}

	$setting_array = highlightdirectspeech_settingarray();
	if ($type == "install") {
		foreach ($setting_array as $name => $setting) {
			$setting['name'] = $name;
			$setting['gid'] = $gid;
			$db->insert_query('settings', $setting);
		}
	} else {
		//array mit settings durchgehen
		foreach ($setting_array as $name => $setting) {
			$setting['name'] = $name;
			$setting['gid'] = $gid;

			//alte einstellung aus der db holen
			$check = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "settings` WHERE name = '{$name}'");
			$check2 = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "settings` WHERE name = '{$name}'");

			$check = $db->num_rows($check);
			//noch gar nicht vorhanden, also hinzufügen
			if ($check == 0) {
				$db->insert_query('settings', $setting);
				echo "Wörtliche Rede: Setting: {$name} wurde hinzugefügt.<br>";
			} else {
				//die einstellung gibt es schon, wir testen ob etwas verändert wurde
				while ($setting_old = $db->fetch_array($check2)) {
					if (
						$setting_old['title'] != $setting['title'] ||
						stripslashes($setting_old['description']) != stripslashes($setting['description']) ||
						$setting_old['optionscode'] != $setting['optionscode'] ||
						$setting_old['disporder'] != $setting['disporder']
					) {
						//wir wollen nicht den zuvor gespeicherten wert ändern, deswegen löschen wir den value vor dem update raus.
						unset($setting['value']);
						$db->update_query('settings', $setting, "name='{$name}'");
						echo "Wörtliche Rede: Setting: {$name} wurde aktualisiert.<br>";
					}
				}
			}
		}
		echo "<p>Einstellungen wurden überprüft.</p>";
	}
	rebuild_settings();
}

function highlightdirectspeech_settingarray()
{
	$settingarray = array();

	$settingarray['highlightdirectspeech_modus'] = array(
		'title' => 'Hervorhebungsmodus',
		'description' => 'Soll das Hervorheben in der Showthread (nur über JS) erfolgen, je nach Einstellung des Users oder soll es beim Erstellen des Posts/Threads direkt in die Textarea eingefügt werden?',
		'optionscode' => "radio\nshowthread=Showthread\nnewthread=Beim Erstellen",
		'value' => 'showthread',
		'disporder' => 0
	);

	$settingarray['highlightdirectspeech_foren'] = array(
		'title' => 'Berücksichtige Foren',
		'description' => 'Wähle die Foren aus, in denen die Hervorhebung der wörtlichen Rede aktiviert sein soll.',
		'optionscode' => 'forumselect',
		'value' => '0', // Default,
		'disporder' => 2
	);

	$settingarray['highlightdirectspeech_css'] = array(
		'title' => 'CSS für Hervorhebung',
		'description' => 'Gib hier das CSS an, welches für die Hervorhebung der wörtlichen Rede verwendet werden soll. Standard ist "font-weight: 900;"',
		'optionscode' => 'textarea',
		'value' => 'font-weight: 900;',
		'disporder' => 3
	);

	return $settingarray;
}

function highlightdirectspeech_add_templates($type = "install")
{
	global $db;
	if ($type == 'install') {
		$templategrouparray = array(
			'prefix' => 'highlightdirectspeech',
			'title'  => $db->escape_string('Wörtliche Rede'),
			'isdefault' => 1
		);
		$db->insert_query("templategroups", $templategrouparray);
	}
	$templates = array();
	$templates = highlightdirectspeech_templates();
	foreach ($templates as $row) {
		$check = $db->num_rows($db->simple_select("templates", "title", "title LIKE '{$row['title']}'"));
		if ($check == 0) {
			$db->insert_query("templates", $row);
			if ($type == 'update') {
				echo "Wörtliche Rede: Neues Template {$row['title']} wurde hinzugefügt.<br>";
			}
		}
	}
}

/**
 * Erstellt ein Array mit den Templates für das Plugin
 * @return array Das Array mit den Templates
 */
function highlightdirectspeech_templates()
{
	global $db;
	$template[] = array(
		'title' => 'highlightdirectspeech_showthread_js',
		'template' => $db->escape_string('
		<style>
		span.directspeech {
			{$css}
		}
	</style>
		<script type="text/javascript" src="{$mybb->asset_url}/jscripts/highlightdirectspeech/highlightdirectspeech.js"></script>
		<script type="text/javascript">
	$(document).ready(function() {
		$(".highlightbutton").click(function(e) {
		console.log("Highlight button clicked");
				e.preventDefault();
				highlightDirectSpeechInPosts();
		});
	});
</script>'),
		'version' => 1,
		'sid' => -2,
		'dateline' => TIME_NOW
	);

	$template[] = array(
		'title' => 'highlightdirectspeech_showthread_js_auto',
		'template' => $db->escape_string('
		<style>
			span.directspeech {
				{$css}
			}
		</style>
		<script type="text/javascript" src="{$mybb->asset_url}/jscripts/highlightdirectspeech/highlightdirectspeech.js"></script>
	<script type="text/javascript">
			$(document).ready(function() {
				highlightDirectSpeechInPosts();
			});
</script>'),
		'version' => 1,
		'sid' => -2,
		'dateline' => TIME_NOW
	);

	$template[] = array(
		'title' => 'highlightdirectspeech_showthread_button',
		'template' => $db->escape_string('<a class="highlightbutton bl-btn bl-btn--showthread" href=""><span class="highlightcaption">Highlight "abc"</span></a>'),
		'version' => 1,
		'sid' => -2,
		'dateline' => TIME_NOW
	);

	$template[] = array(
		'title' => 'highlightdirectspeech_post',
		'template' => $db->escape_string('<tr><td class="trow1"><label for="tagInput">HTML-Tag für wörtliche Rede:</label></td>
	<td class="trow1">
<input id="tagInput" type="text" style="width:300px;" placeholder="<b> oder z.B. <span style=\'color:red;\'>">
<button type="button" id="convert">Wörtliche Rede formatieren</button>{$jscript}</td></tr>'),
		'version' => 1,
		'sid' => -2,
		'dateline' => TIME_NOW
	);

	$template[] = array(
		'title' => 'highlightdirectspeech_ucp',
		'template' => '
		<fieldset class="trow2">
		<legend><strong>Wörtliche Rede in Posts</strong></legend>
		<table cellspacing="0" cellpadding="{$theme[\\\'tablespace\\\']}">
		<tr>
		<td colspan="2"><span class="smalltext"><strong>Möchtest du wörtliche Rede in Posts fett hervorheben? Du kannst auswählen, ob dies automatisch geschehen soll, bei Klick auf einen Button, oder gar nicht.</strong></span></td>
		</tr>
			<tr>
			<td colspan="2">
				<select name="directspeach" id="directspeach" >
					<option value="1" {$check_auto}>Automatisch</option>
					<option value="2" {$check_button}>Button</option>
					<option value="0" {$check_no}>Gar nicht</option>
				</select>
					</td>
		</tr>
		</table>
		</fieldset>
		',
		'version' => 1,
		'sid' => -2,
		'dateline' => TIME_NOW
	);
	return $template;
}

function highlightdirectspeech_uninstall()
{
	global $db;
	if ($db->field_exists("directspeach", "users")) {
		$db->write_query("ALTER TABLE " . TABLE_PREFIX . "users DROP directspeach");
	}
	// Einstellungen entfernen
	$db->delete_query("settings", "name LIKE 'highlightdirectspeech%'");
	$db->delete_query('settinggroups', "name = 'highlightdirectspeech'");

	$db->delete_query('templates', "title like 'highlightdirectspeech%'");
	$db->delete_query("templategroups", "prefix = 'highlightdirectspeech'");
}

function highlightdirectspeech_activate()
{
	global $db;

	require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

	find_replace_templatesets('usercp_profile', '#{\$contactfields}#', "{\$contactfields}\n{\$highlightdirectspeech_ucp}");
	find_replace_templatesets('showthread', '#{\$headerinclude}#', "{\$headerinclude}\n{\$highlightdirectspeech_js}");
	find_replace_templatesets('showthread', '#{\$headerinclude}#', "{\$headerinclude}\n{\$showthread_highlightdirectspeech_js_auto}");
	find_replace_templatesets('showthread', '#{\$newreply}#', "{\$highlightdirectspeech_button}{\$newreply}");
	find_replace_templatesets("newreply", "#" . preg_quote('{$postoptions}') . "#i", '{$add_html_tags}{$postoptions}');
	find_replace_templatesets("newthread", "#" . preg_quote('{$postoptions}') . "#i", '{$add_html_tags}{$postoptions}');
}

function highlightdirectspeech_deactivate()
{
	global $db;

	require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
	find_replace_templatesets('usercp_profile', '#{\$highlightdirectspeech_ucp}#', "");
	find_replace_templatesets('showthread', '#{\$highlightdirectspeech_js_auto}(\n?)#', '', 0);
	find_replace_templatesets('showthread', '#{\$highlightdirectspeech_js}(\n?)#', '', 0);
	find_replace_templatesets('showthread', '#{\$highlightdirectspeech_button}(\n?)#', '', 0);
	find_replace_templatesets("newreply", "#" . preg_quote('{$add_html_tags}') . "#i", '');
	find_replace_templatesets("newthread", "#" . preg_quote('{$add_html_tags}') . "#i", '');
}


$plugins->add_hook("showthread_end", "show_highlightdirectspeech");
function show_highlightdirectspeech()
{
	global $db, $fid, $mybb, $templates, $lang, $highlightdirectspeech_js, $highlightdirectspeech_button, $showthread_highlightdirectspeech_js_auto;
	//INGAME Bereiche
	$highlightdirectspeech_js_auto = "";
	$highlightdirectspeech_js = "";
	$highlightdirectspeech_button = "";
	$css = "";
	$fids = $mybb->settings['highlightdirectspeech_foren'];
	if ($mybb->settings['highlightdirectspeech_modus'] == "showthread") {
		$css = $mybb->settings['highlightdirectspeech_css'];
		$parentlist = get_parent_list($fid);
		//Im Ingame ja / nein? ^
		$fidarray = explode(",", $fids);

		foreach ($fidarray as $id) {
			if (strpos("," . $parentlist . ",", "," . trim($id) . ",") !== false) {
				$fidflag = true;
			}
		}
		if ($fids == "-1") {
			$fidflag = true;
		}
		if ($fidflag) {
			//einstellungen des Users holen
			if ($mybb->user['directspeach'] == 1) {
				//immer anzeigen
				eval("\$showthread_highlightdirectspeech_js_auto = \"" . $templates->get("highlightdirectspeech_showthread_js_auto") . "\";");
			} else if ($mybb->user['directspeach'] == 2) {
				//button anzeigen
				eval("\$highlightdirectspeech_js = \"" . $templates->get("highlightdirectspeech_showthread_js") . "\";");
				eval("\$highlightdirectspeech_button = \"" . $templates->get("highlightdirectspeech_showthread_button") . "\";");
			} else {
				//gar nicht
				$highlightdirectspeech_js_auto = "";
				$highlightdirectspeech_js = "";
				$highlightdirectspeech_button = "";
			}
		}
	}
}

$plugins->add_hook('newthread_start', 'highlightdirectspeech_post');
$plugins->add_hook('newreply_start', 'highlightdirectspeech_post');
function highlightdirectspeech_post()
{
	global $mybb, $add_html_tags, $templates, $fid;
	$fids = $mybb->settings['highlightdirectspeech_foren'];
	$parentlist = get_parent_list($fid);
	$add_html_tags = "";
	$jscript = "";

	$fidarray = explode(",", $fids);
	foreach ($fidarray as $id) {
		if (strpos("," . $parentlist . ",", "," . trim($id) . ",") !== false) {
			$fidflag = true;
		}
	}
	if ($fids == "-1") {
		$fidflag = true;
	}
	if ($fidflag && $mybb->settings['highlightdirectspeech_modus'] == "newthread") {
		$jscript = "<script>
					document.getElementById('convert').addEventListener('click', function() {
					const textarea = document.getElementById('message');
					const inputTag = document.getElementById('tagInput').value.trim();
					const text = textarea.value;
					
					if (!inputTag.startsWith('<') || !inputTag.endsWith('>')) {
						alert(\"Bitte einen gültigen HTML-Tag eingeben, z.B. <b> oder <span style='color:red;'>\");
						return;
					}
					
					// Tagname extrahieren (alles zwischen < und erstem Leerzeichen oder >)
					const match = inputTag.match(/^<\\s*([a-z0-9]+)/i);
					if (!match) {
						alert(\"HTML-Tag konnte nicht erkannt werden.\");
						return;
					}
					const tagName = match[1];
					const closingTag = `</\${tagName}>`;

					// Regex für verschiedene Anführungszeichen, inklusive der Zeichen selbst:
					const quoteRegex = /(„[^“]+“|“[^”]+”|\"[^\"]+\"|«[^»]+»)/g;

					const replaced = text.replace(quoteRegex, (match) => {
						return `\${inputTag}\${match}\${closingTag}`;
					});

					textarea.value = replaced;
				});
				</script>";
		eval("\$add_html_tags=\"" . $templates->get("highlightdirectspeech_post") . "\";");
	}
}


$plugins->add_hook('usercp_profile_start', 'highlightdirectspeech_edit_profile');
function highlightdirectspeech_edit_profile()
{
	global $mybb, $db, $templates, $highlightdirectspeech_ucp;
	$check_auto = $check_button = $check_no = "";
	$highlightdirectspeech_ucp = "";

	if ($mybb->settings['highlightdirectspeech_modus'] == "showthread") {
		$check = $db->fetch_field($db->simple_select("users", "directspeach", "uid = " . $mybb->user['uid'] . ""), "directspeach");

		if ($check == 1) {
			$check_auto = ' SELECTED ';
			$check_button = "";
			$check_no = "";
		} else if ($check == 2) {
			$check_auto = "";
			$check_button = ' SELECTED ';
			$check_no = "";
		} else {
			$check_auto = "";
			$check_button = "";
			$check_no = ' SELECTED ';
		}
		eval("\$highlightdirectspeech_ucp.=\"" . $templates->get("highlightdirectspeech_ucp") . "\";");
	}
}

$plugins->add_hook('usercp_do_profile_start', 'highlightdirectspeech_edit_profile_do');
function highlightdirectspeech_edit_profile_do()
{
	global $mybb, $db;
	$highlightdirectspeech_check =  $mybb->get_input('directspeach', MYBB::INPUT_INT);

	//FUNCTION FROM Character ALERT used
	$uid = highlightdirectspeech_getCharacters();

	$db->query("UPDATE " . TABLE_PREFIX . "users SET directspeach =" . $highlightdirectspeech_check . " WHERE uid IN (" . $uid . ")");
}


/**
 * Get the shared Accounts from Accountswitcher
 * @return string all_uids 
 */
function highlightdirectspeech_getCharacters()
{
    global $db, $mybb;
    $thisuser = (int) $mybb->user['uid'];
	if ($db->field_exists("as_uid", "users")) {

    $as_uid = (int)($mybb->user['as_uid'] ?? 0);
    $all = array();
    if ($as_uid == 0) {
        $hauptchar = $thisuser;
        $get_all_uids = $db->query("SELECT uid FROM " . TABLE_PREFIX . "users WHERE 
			 		   ((as_uid=$thisuser) OR (uid=$thisuser)) ORDER BY username");
    } else if ($as_uid != 0) { //nicht mit Hauptaccoung online
        //id des users holen wo alle angehangen sind + alle charas
        $hauptchar = $as_uid;
        $get_all_uids = $db->query("SELECT uid FROM " . TABLE_PREFIX . "users WHERE 
					  ((as_uid=$as_uid) OR (uid=$thisuser) OR (uid=$as_uid)) 
			 		  ORDER BY username");
    }

    while ($uid = $db->fetch_array($get_all_uids)) {
        array_push($all, $uid['uid']);
    }
    $all_ids = implode(',', $all);
	} else {
		$all_ids = $thisuser;
	}

    return $all_ids;
}
