<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function alexafiretv_install()
{
    alexafiretv_getIsoWidget();
}

function alexafiretv_update()
{
    message::add('alexafiretv', 'Mise à jour du plugin Fire TV terminée.');
    alexafiretv_getIsoWidget();
}

/*
 * Pack de widgets iso (github.com/limad/widget-iso), partagé par plusieurs plugins.
 * Fonctions préfixées par le plugin : Jeedom charge les install.php de plusieurs plugins
 * dans le même processus, un nom commun ferait tourner la copie (et le log) d'un autre.
 * Version figée : l'archive est prise sur un tag du dépôt et n'est installée que si son
 * sha256 est celui attendu. L'empreinte installée est notée dans widget_iso_version.json :
 * aucun accès réseau si ce pack (ou une version plus récente) est déjà en place.
 * Compatible PHP 7.4 (Debian 11).
 */
function alexafiretv_getIsoWidget()
{
	$pluginId = 'alexafiretv';
	// Nouvelle version du pack : pousser un tag sur widget-iso, puis changer ces trois valeurs dans chaque plugin.
	$packVersion = '2026.11.08';
	$packRef     = 'v2026.11.08';
	$packSha256  = 'acba8938a596adaed5fee1162ec3eacd1c78c3feea7ac5b078f5f85059d1d58c';
	$zipPath  = '';
	$unzipDir = '';
	$installed = false;
	try {
		$dest = __DIR__ . '/../../../data/customTemplates/dashboard';
		if (!is_dir($dest)) {
			@mkdir($dest, 0775, true);
		}
		$dest = realpath($dest);
		if ($dest === false || !is_dir($dest)) {
			log::add($pluginId, 'error', 'getIsoWidget::Répertoire des widgets introuvable : data/customTemplates/dashboard');
			return;
		}
		$marker = $dest . '/widget_iso_version.json';
		$local = is_file($marker) ? json_decode((string)@file_get_contents($marker), true) : null;
		if (!is_array($local)) {
			$local = array();
		}
		$found = glob($dest . '/cmd.*.iso_*.html');
		$installed = is_file($dest . '/widget_iso_by_limad44.txt') || (is_array($found) && count($found) > 0);

		// 1. Rien à faire si ce pack, une version plus récente ou le gestionnaire iso_widgets est déjà en place
		if ($installed) {
			$skip = '';
			$localVersion = (isset($local['version']) && is_string($local['version'])) ? $local['version'] : '';
			if (isset($local['sha256']) && $local['sha256'] === $packSha256) {
				$skip = 'Widgets iso ' . $packVersion . ' déjà installés';
			} elseif (isset($local['plugin']) && $local['plugin'] === 'iso_widgets' && (int)config::byKey('active', 'iso_widgets', 0) === 1) {
				$skip = 'Widgets iso gérés par le plugin iso_widgets';
			} elseif (preg_match('/^\d+(\.\d+)*$/', $localVersion) && version_compare($localVersion, $packVersion, '>=')) {
				$skip = 'Widgets iso ' . $localVersion . ' déjà installés (version embarquée : ' . $packVersion . ')';
			}
			if ($skip !== '') {
				log::add($pluginId, 'debug', 'getIsoWidget::' . $skip . ', aucun téléchargement');
				return;
			}
		}

		// 2. Téléchargement du tag : raw.githubusercontent.com, puis l'API GitHub (autre hôte) en secours.
		// Seule une archive dont le sha256 est celui attendu est acceptée.
		$errors = array();
		$data = null;
		$sources = array(
			array('raw.githubusercontent.com', 'https://raw.githubusercontent.com/limad/widget-iso/' . $packRef . '/widget_iso.zip', array()),
			array('api.github.com', 'https://api.github.com/repos/limad/widget-iso/contents/widget_iso.zip?ref=' . $packRef, array('Accept: application/vnd.github.raw')),
		);
		foreach ($sources as $src) {
			$r = alexafiretv_isoWidgetHttp($src[1], $src[2]);
			if (!$r['ok']) {
				$errors[] = $src[0] . ' : ' . $r['error'];
			} elseif (!hash_equals($packSha256, hash('sha256', $r['body']))) {
				$errors[] = $src[0] . ' : empreinte sha256 inattendue (' . strlen($r['body']) . ' octets reçus)';
			} else {
				$data = $r['body'];
				break;
			}
		}
		if ($data === null) {
			$msg = 'getIsoWidget::Téléchargement des widgets iso ' . $packVersion . ' impossible (' . implode(' ; ', $errors) . ')';
			if ($installed) {
				log::add($pluginId, 'warning', $msg . ', les widgets déjà installés sont conservés');
			} else {
				log::add($pluginId, 'error', $msg);
			}
			return;
		}

		// 3. Extraction dans un dossier neuf, chemins de l'archive vérifiés avant
		$tmpFolder = jeedom::getTmpFolder($pluginId);
		$zipPath   = $tmpFolder . '/widget-iso.zip';
		$unzipDir  = $tmpFolder . '/widget-iso';
		alexafiretv_isoWidgetRmdir($unzipDir);
		if (file_put_contents($zipPath, $data) === false) {
			log::add($pluginId, 'error', 'getIsoWidget::Écriture impossible : ' . $zipPath);
			return;
		}
		$zip = new ZipArchive();
		if ($zip->open($zipPath) !== true) {
			log::add($pluginId, 'error', 'getIsoWidget::Archive ZIP illisible');
			return;
		}
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$name = (string)$zip->getNameIndex($i);
			if ($name === '' || $name[0] === '/' || $name[0] === '\\' || strpos($name, '..') !== false || strpos($name, ':') !== false) {
				$zip->close();
				log::add($pluginId, 'error', 'getIsoWidget::Chemin refusé dans l\'archive : ' . $name);
				return;
			}
		}
		@mkdir($unzipDir, 0775, true);
		$extracted = $zip->extractTo($unzipDir);
		$zip->close();
		$root = realpath($unzipDir);
		if (!$extracted || $root === false) {
			log::add($pluginId, 'error', 'getIsoWidget::Extraction de l\'archive impossible');
			return;
		}
		$list = scandir($root);
		$sub = is_array($list) ? array_values(array_diff($list, array('.', '..'))) : array();
		if (count($sub) === 0) {
			log::add($pluginId, 'error', 'getIsoWidget::Archive vide');
			return;
		}
		if (count($sub) === 1 && is_dir($root . '/' . $sub[0])) {
			$root .= '/' . $sub[0];
		}

		// 4. Copie des seuls fichiers changés (custom.* existants jamais écrasés), puis empreinte notée
		$copied = alexafiretv_isoWidgetCopy($root, $dest, $pluginId);
		alexafiretv_isoWidgetWriteMarker($marker, $packVersion, $packRef, $packSha256, strlen($data), $pluginId);
		log::add($pluginId, 'info', 'getIsoWidget::Widgets iso ' . $packVersion . ' installés (' . $copied . ' fichier(s) mis à jour)');
	} catch (\Throwable $e) {
		log::add($pluginId, $installed ? 'warning' : 'error', 'getIsoWidget::' . $e->getMessage());
	} finally {
		if ($unzipDir !== '') {
			alexafiretv_isoWidgetRmdir($unzipDir);
		}
		if ($zipPath !== '') {
			@unlink($zipPath);
		}
	}
}

/** Requête HTTPS GET ; renvoie ok, code, body et la cause en cas d'échec. */
function alexafiretv_isoWidgetHttp($url, $headers = array())
{
	$res = array('ok' => false, 'code' => 0, 'body' => '', 'error' => '');
	if (!function_exists('curl_init')) {
		$res['error'] = 'extension PHP curl absente';
		return $res;
	}
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER  => true,
		CURLOPT_FOLLOWLOCATION  => true,
		CURLOPT_MAXREDIRS       => 5,
		CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS,
		CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
		CURLOPT_SSL_VERIFYPEER  => true,
		CURLOPT_SSL_VERIFYHOST  => 2,
		CURLOPT_CONNECTTIMEOUT  => 15,
		CURLOPT_TIMEOUT         => 90,
		CURLOPT_MAXFILESIZE     => 20 * 1024 * 1024,
		CURLOPT_USERAGENT       => 'jeedom-alexafiretv (widget-iso)',
		CURLOPT_HTTPHEADER      => $headers,
	));
	$body = curl_exec($ch);
	$res['code'] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($body === false) {
		$res['error'] = 'cURL ' . curl_errno($ch) . ' ' . curl_error($ch);
	} elseif ($res['code'] !== 200) {
		$res['error'] = 'HTTP ' . $res['code'];
	} else {
		$res['ok'] = true;
		$res['body'] = (string)$body;
	}
	unset($ch);
	return $res;
}

/** Copie récursive des fichiers de rendu ; renvoie le nombre de fichiers écrits. */
function alexafiretv_isoWidgetCopy($src, $dst, $pluginId)
{
	$allowed = array('html', 'js', 'css', 'map', 'md', 'txt', 'json', 'woff', 'woff2', 'ttf', 'png', 'jpg', 'jpeg', 'gif', 'svg');
	$count = 0;
	$items = scandir($src);
	if (!is_array($items)) {
		return 0;
	}
	foreach ($items as $item) {
		if ($item === '.' || $item === '..' || $item === '.htaccess') {
			continue;
		}
		$s = $src . '/' . $item;
		$d = $dst . '/' . $item;
		if (is_link($s)) {
			continue;
		}
		if (is_dir($s)) {
			if (!is_dir($d)) {
				@mkdir($d, 0775, true);
			}
			$count += alexafiretv_isoWidgetCopy($s, $d, $pluginId);
			continue;
		}
		if (!in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), $allowed, true)) {
			continue;
		}
		if (is_file($d) && (strpos($item, 'custom.') === 0 || (filesize($d) === filesize($s) && md5_file($d) === md5_file($s)))) {
			continue; // personnalisé par l'utilisateur, ou inchangé
		}
		if (@copy($s, $d)) {
			$count++;
		} else {
			log::add($pluginId, 'warning', 'getIsoWidget::Copie impossible : ' . $item);
		}
	}
	return $count;
}

function alexafiretv_isoWidgetWriteMarker($marker, $version, $ref, $sha, $size, $pluginId)
{
	$json = json_encode(array('version' => $version, 'ref' => $ref, 'sha256' => $sha, 'size' => $size, 'date' => date('c'), 'plugin' => $pluginId));
	if (@file_put_contents($marker, $json, LOCK_EX) === false) {
		log::add($pluginId, 'debug', 'getIsoWidget::Empreinte non enregistrée (' . $marker . ')');
	}
}
function alexafiretv_isoWidgetRmdir($dir)
{
	if (!is_dir($dir)) {
		return;
	}
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($items as $item) {
		if ($item->isDir() && !$item->isLink()) {
			@rmdir($item->getPathname());
		} else {
			@unlink($item->getPathname());
		}
	}
	@rmdir($dir);
}

