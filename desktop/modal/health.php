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

if (!isConnect('admin')) {
	throw new Exception('401 Unauthorized');
}

$eqLogics = alexafiretv::byType('alexafiretv');
?>
<table class="table table-condensed tablesorter" id="table_healthNetwork">
	<thead>
		<tr>
			<th>{{Module}}</th>
			<th>{{ID}}</th>
			<th>{{Adresse IP}}</th>
			<th>{{Présent}}</th>
			<th>{{Allumé}}</th>
		</tr>
	</thead>
	<tbody>
<?php
foreach ($eqLogics as $eqLogic) {
	$ip = htmlspecialchars((string) $eqLogic->getConfiguration('adresseip'), ENT_QUOTES, 'UTF-8');
	if ($eqLogic->getStatus('online') == true) {
		$present = '<span class="label label-success" style="font-size : 1em;" title="{{Présent}}"><i class="fas fa-check-circle"></i></span>';
	} else {
		$present = '<span class="label label-danger" style="font-size : 1em;" title="{{Absent}}"><i class="fas fa-times-circle"></i></span>';
	}
	if ($eqLogic->getStatus('on') == true) {
		$on = '<span class="label label-success" style="font-size : 1em;" title="{{Allumé}}"><i class="fas fa-toggle-on"></i></span>';
	} else {
		$on = '<span class="label label-default" style="font-size : 1em;" title="{{Éteint}}"><i class="fas fa-toggle-off"></i></span>';
	}
	echo '<tr><td><a href="' . $eqLogic->getLinkToConfiguration() . '" style="text-decoration: none;">' . $eqLogic->getHumanName(true) . '</a></td>';
	echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqLogic->getId() . '</span></td>';
	echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . ($ip !== '' ? $ip : '{{Non configurée}}') . '</span></td>';
	echo '<td>' . $present . '</td>';
	echo '<td>' . $on . '</td></tr>';
}
?>
	</tbody>
</table>
