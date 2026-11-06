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
    getIsoWidget('alexafiretv');
}

function alexafiretv_update()
{
    message::add('alexafiretv', 'Mise à jour du plugin Fire TV terminée.');
    getIsoWidget('alexafiretv');
}

if (!function_exists('getIsoWidget')) {
/**
 * Récupération du Widget ISO
 */
function getIsoWidget($pluginId = 'alexafiretv')
{
	log::add($pluginId, 'debug', "getIsoWidget::Début de la récupération du widget ISO");

	try {
		$finalDest = realpath(__DIR__ . '/../../../data/customTemplates/dashboard');
		if (!$finalDest || !is_dir($finalDest)) {
			log::add($pluginId, 'error', "getIsoWidget::Répertoire de destination introuvable : $finalDest");
			return;
		}

		$tmpFolder = jeedom::getTmpFolder($pluginId);
		$zipPath   = $tmpFolder . "/widget-iso.zip";
		$unzipDir  = $tmpFolder . "/widget-iso";

		// Téléchargement via cURL (timeout explicite + User-Agent + follow redirects)
		$url = "https://github.com/limad/widget-iso/raw/beta/widget_iso.zip";
		$ch  = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_TIMEOUT        => 60,
			CURLOPT_USERAGENT      => 'jeedom-mcp-installer/1.0',
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
		]);
		$data     = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlErr  = curl_error($ch);
		curl_close($ch);
		if ($data === false || $httpCode !== 200) {
			log::add($pluginId, 'error', "getIsoWidget::Echec telechargement ZIP (HTTP $httpCode) $curlErr");
			return;
		}
		// Defense-in-depth : taille plausible (1 Ko - 20 Mo)
		$size = strlen($data);
		if ($size < 1024 || $size > 20 * 1024 * 1024) {
			log::add($pluginId, 'error', "getIsoWidget::Taille ZIP suspecte ($size octets), abandon");
			return;
		}

		if (file_put_contents($zipPath, $data) === false) {
			log::add($pluginId, 'error', "getIsoWidget::Impossible d'écrire le fichier ZIP dans $zipPath");
			return;
		}

		// Extraction — avec protection Zip Slip (valide chaque entry avant extraction)
		$zip = new ZipArchive;
		if ($zip->open($zipPath) === TRUE) {
			@mkdir($unzipDir, 0755, true);
			$realUnzipDir = realpath($unzipDir);
			if ($realUnzipDir === false) {
				log::add($pluginId, 'error', "getIsoWidget::Repertoire extraction introuvable : $unzipDir");
				$zip->close();
				return;
			}
			for ($i = 0; $i < $zip->numFiles; $i++) {
				$entryName = $zip->getNameIndex($i);
				if ($entryName === false) continue;
				// Rejet des chemins absolus ou traversant (..)
				if ($entryName === '' || $entryName[0] === '/' || strpos($entryName, '..') !== false) {
					log::add($pluginId, 'error', "getIsoWidget::Entree ZIP suspecte, abandon : $entryName");
					$zip->close();
					return;
				}
			}
			$zip->extractTo($unzipDir);
			$zip->close();
			// Defense-in-depth : verifier qu'aucun fichier n'a ete ecrit hors du dossier
			$iter = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($realUnzipDir, RecursiveDirectoryIterator::SKIP_DOTS)
			);
			foreach ($iter as $f) {
				$rp = realpath($f->getPathname());
				if ($rp === false || strpos($rp, $realUnzipDir . DIRECTORY_SEPARATOR) !== 0) {
					log::add($pluginId, 'error', "getIsoWidget::Fichier extrait hors sandbox : $rp");
					removeDirectory($realUnzipDir);
					return;
				}
			}
		} else {
			log::add($pluginId, 'error', "getIsoWidget::Impossible d'ouvrir le fichier ZIP");
			return;
		}

		// Détection du dossier racine dans le ZIP
		$sub = array_values(array_diff(scandir($unzipDir), ['.', '..']));
		if (empty($sub)) {
			log::add($pluginId, 'error', "getIsoWidget::ZIP vide ou extraction échouée");
			return;
		}

		$rootPath = (is_dir($unzipDir . '/' . $sub[0])) ? $unzipDir . '/' . $sub[0] : $unzipDir;

		// Copie vers la destination finale
		copyDirectoryFiltered($rootPath, $finalDest, $pluginId);

		// Nettoyage
		removeDirectory($unzipDir);
		@unlink($zipPath);
	} catch (Throwable $e) {
		log::add($pluginId, 'error', "getIsoWidget::Failed: " . $e->getMessage());
	}
}
}

if (!function_exists('copyDirectoryFiltered')) {
/**
 * Copie récursive avec protection des fichiers personnalisés
 */
function copyDirectoryFiltered($src, $dst, $pluginId = 'alexafiretv')
{
	if (!is_dir($src)) return;

	$items = scandir($src);
	foreach ($items as $item) {
		if ($item === '.' || $item === '..') continue;
		$srcPath = $src . '/' . $item;
		$dstPath = $dst . '/' . $item;

		if (is_dir($srcPath)) {
			if (!is_dir($dstPath)) {
				@mkdir($dstPath, 0755, true);
			}
			copyDirectoryFiltered($srcPath, $dstPath, $pluginId);
		} else {
			// On ne remplace pas les fichiers "custom.*" existants
			if (substr($item, 0, 7) === 'custom.' && is_file($dstPath)) {
				continue;
			}
			if (!@copy($srcPath, $dstPath)) {
				log::add($pluginId, 'warning', "copyDirectoryFiltered::Copie impossible : $srcPath -> $dstPath");
			}
		}
	}
}
}

if (!function_exists('removeDirectory')) {
/**
 * Suppression récursive d'un dossier
 */
function removeDirectory($dir)
{
	if (!is_dir($dir)) return;
	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($files as $fileinfo) {
		$todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
		@$todo($fileinfo->getRealPath());
	}
	@rmdir($dir);
}
}
