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

/* Vanilla JS (la box tourne en core::jqueryless : ni jQuery ni bootbox). */

function appendCmdRow(html) {
  var tbody = document.querySelector('#table_cmd tbody');
  if (!tbody) return null;
  var tmp = document.createElement('tbody');
  tmp.innerHTML = html;
  var row = tmp.firstElementChild;
  tbody.appendChild(row);
  return row;
}

document.addEventListener('click', function (event) {
  if (event.target.closest('#bt_forcerDefaultCmd')) {
    jeeDialog.confirm({
      title: '{{Recharger la configuration par défaut ?}}',
      message: '<label class="lbl lbl-warning">{{Notez que cette fonction ne supprime pas votre équipement ni ses commandes, cela évite d\'avoir à refaire les scénarios. Elle va supprimer toute personnalisation et toutes les commandes vont revenir à leur état initial.}}</label>',
      buttons: { confirm: { label: '{{Démarrer}}' } }
    }, function (result) {
      if (!result) return;
      var eqId = document.querySelector('.eqLogicAttr[data-l1key=id]').jeeValue();
      domUtils.ajax({
        type: "POST",
        url: "plugins/alexafiretv/core/ajax/alexafiretv.ajax.php",
        data: {
          action: "forcerDefaultCmd",
          id: eqId,
          createcommand: 0,
        },
        dataType: 'json',
        global: false,
        error: function (request, status, error) {
          handleAjaxError(request, status, error);
        },
        success: function (data) {
          if (data.state != 'ok') {
            jeedomUtils.showAlert({message: data.result, level: 'danger'});
            return;
          }
          jeedomUtils.showAlert({message: '{{Opération réalisée avec succès}}', level: 'success'});
          document.querySelector('.eqLogicDisplayCard[data-eqLogic_id="' + eqId + '"]')?.click();
        }
      });
    });
    return;
  }

  if (event.target.closest('#bt_alexafiretvForcerDefault')) {
    var eqId = document.querySelector('.eqLogicAttr[data-l1key="id"]')?.jeeValue() || document.querySelector('.eqLogicAttr[data-l1key="id"]')?.value;
    if (!eqId) return;
    jeeDialog.confirm({
      title: '{{Réinitialiser les widgets}}',
      message: '{{Voulez-vous réinitialiser les widgets et retours à la ligne par défaut sur toutes les commandes de cet équipement ?}}',
      callback: function (result) {
        if (!result) return;
        domUtils.ajax({
          type: 'POST',
          url: 'plugins/alexafiretv/core/ajax/alexafiretv.ajax.php',
          data: {
            action: 'resetIsoTemplates',
            id: eqId
          },
          dataType: 'json',
          error: function (request, status, error) {
            handleAjaxError(request, status, error);
          },
          success: function (data) {
            if (data.state != 'ok') {
              jeedomUtils.showAlert({ message: data.result, level: 'danger' });
              return;
            }
            if (typeof toastr !== 'undefined') toastr.success('{{Widgets ISO réinitialisés avec succès}}');
            window.location.reload();
          }
        });
      }
    });
    return;
  }

  if (event.target.closest('#bt_resetEqlogicSearch2')) {
    var input = document.getElementById('in_searchEqlogic2');
    if (input) {
      input.value = '';
      input.dispatchEvent(new Event('keyup', {bubbles: true}));
    }
    return;
  }

  if (event.target.closest('#bt_addAction')) {
    addCmdToTable({type: 'action'});
    return;
  }

  if (event.target.closest('#bt_addInfo')) {
    addCmdToTable({type: 'info', subType: 'string'});
    return;
  }

  if (event.target.closest('#bt_sante')) {
    jeeDialog.dialog({
      title: "{{Liste Amazon Fire TV}}",
      contentUrl: 'index.php?v=d&plugin=alexafiretv&modal=health'
    });
  }
});

document.addEventListener('keyup', function (event) {
  var input = event.target.closest('#in_searchEqlogic2');
  if (!input) return;
  var search = input.value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, "");
  document.querySelectorAll('.eqLogicDisplayCard.second').forEach(function (card) {
    if (search == '') {
      card.style.display = '';
      return;
    }
    var nameEl = card.querySelector('.name');
    var text = (nameEl ? nameEl.textContent : '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, "");
    card.style.display = text.indexOf(search) >= 0 ? '' : 'none';
  });
});

document.addEventListener('change', function (event) {
  if (!event.target.closest('.eqLogicAttr[data-l1key=configuration][data-l2key=type]')) return;
  // Ici il faudra faire comme dans desktop/php/alexafiretv.php ligne 268 chercher type et à défaut family
  var icon = document.querySelector('.eqLogicAttr[data-l1key=configuration][data-l2key=type]').jeeValue();
  if (icon != '' && icon != null) {
    document.getElementById('img_device')?.setAttribute('src', 'plugins/alexaapi/core/config/devices/' + icon + '.png');
  }

  var id = document.querySelector('.eqLogicAttr[data-l1key=id]').value;
  if (id) {
    jeedom.eqLogic.byId({
      id: id,
      noCache: true,
      success: function (obj) {
        if (obj && obj.configuration && obj.configuration.capabilities && obj.configuration.capabilities instanceof Array && obj.configuration.capabilities.length) {
          var caps = document.querySelector('.eqLogicAttr[data-l1key=configuration][data-l2key=capabilities]');
          if (caps) caps.innerHTML = obj.configuration.capabilities.join(', ');
        }
      }
    });
  }
});

(function () {
  var tbody = document.querySelector('#table_cmd tbody');
  if (tbody && typeof Sortable !== 'undefined' && !tbody.dataset.sortableInit) {
    tbody.dataset.sortableInit = '1';
    new Sortable(tbody, {draggable: '.cmd', direction: 'vertical'});
  }
})();

function addCmdToTable(_cmd) {
  if (!isset(_cmd))
    var _cmd = {
      configuration: {}
    };

  var DefinitionDivPourCommandesPredefinies = 'style="display: none;"';
  if (init(_cmd.logicalId) == "")
    DefinitionDivPourCommandesPredefinies = "";

  //console.log("addCmdToTable : " + init(_cmd.logicalId));

  if (init(_cmd.type) == 'info') {
    var tr =
      '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">' +
      '<td>' +
      '<small><small><span class="cmdAttr" data-l1key="id"></span></small></small>' +
      '</td>' +
      '<td>' +
      '<div class="row">' +
      '<div class="col-lg-1">'
      //    +         '<a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon"><i class="fas fa-flag"></i> Icone</a>'
      +
      '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>' +
      '</div></td><td>' +
      '<div class="col-lg-8">' +
      '<input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom du capteur}}"></td>' +
      '<td>'
      //     +     '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>'
      +
      '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="info" disabled style="margin-bottom : 5px;" />'
           +     '<div ' + DefinitionDivPourCommandesPredefinies + '>'
           +     '<span class="subType" subType="' + init(_cmd.subType) + '"></span></div>'
      +
      '</td>' +
      '<td><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="request">'
      //     +     '<small><span class="cmdAttr"  data-l1key="configuration" data-l2key="cmd"></span> Résultat de la commande <span class="cmdAttr"  data-l1key="configuration" data-l2key="taskname"></span> (<span class="cmdAttr"  data-l1key="configuration" data-l2key="taskid"></span>)</small>'

      //    +     '<span class="cmdAttr"  data-l1key="configuration" data-l2key="value"></span>'
      +
      '</td>' +
      '<td>'
      //   +     '<input class="cmdAttr form-control input-sm" data-l1key="unite" style="width : 90px;" placeholder="{{Unite}}">'
      +
      '</td>' +
      '<td>' +
      '</td>' +
      '<td>' +
      '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ' +
      '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ' +
      '</td>' +
      '<td>';

    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fas fa-cogs"></i></a> ' +
        '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }

    tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>' +
      '</td>' +
      '</tr>';

    var row = appendCmdRow(tr);
    if (row) row.setJeeValues(_cmd, '.cmdAttr');
  }

  if (init(_cmd.type) == 'action') {
    var tr =
      '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">' +
      '<td>' +
      '<small><small><span class="cmdAttr" data-l1key="id"></span></small></small>' +
      '</td>' +
      '<td>' +
      '<div class="row">' +
      '<div class="col-lg-1">' +
      '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>' +
      '</div></td><td>' +
      '<div class="col-lg-8">' +
      '<input class="cmdAttr form-control input-sm" data-l1key="name">' +
      '</div>' +
      '</div>';

    tr += '</td>';

    tr += '<td>';
    tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="action" disabled />';
    tr += '<div ' + DefinitionDivPourCommandesPredefinies + '>';
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
    tr += '</div></td>';
    tr += '<td>' +
      '<input class="cmdAttr form-control input-sm"';
   // if (init(_cmd.logicalId) != "")
   //   tr += 'readonly';

    if (init(_cmd.logicalId) == "refresh")
      tr += ' style="display:none;" ';

    tr += ' data-l1key="configuration" data-l2key="request">';

    if (init(_cmd.subType) == 'select') {
      tr += '<input class="tooltips cmdAttr form-control input-sm expertModeVisible" data-l1key="configuration" data-l2key="listValue" placeholder="{{Liste de valeur|texte séparé par ;}}" title="{{Liste}}">';
    }

    tr += '</td>';
    tr += '<td>';

    if ((init(_cmd.logicalId) == "") || (init(_cmd.logicalId) == "volume")) {
      tr += '<input class="cmdAttr form-control input-sm" data-l1key="unite"  style="width : 100px;" placeholder="{{Unité}}" title="{{Unité}}" >';
      tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}"  title="{{Min}} style="margin-top : 3px;"> ';
      tr += '</td>';
      tr += '<td>';
      tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}} style="margin-top : 3px;">';
    } else {
      tr += '</td>';
      tr += '<td>';
    }

    tr += '</td>' +
      '<td>' +
      '<input class="tooltips cmdAttr form-control input-sm expertModeVisible" data-l1key="configuration" data-l2key="listValue" placeholder="{{Liste de valeur|texte séparé par ;}}" title="{{Liste}}" style="margin-top : 5px;">' +
      '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ' +
      '</td>' +
      '<td>';

    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fas fa-cogs"></i></a> ';
      if (!((init(_cmd.name) == "Routine") || (init(_cmd.name) == "xxxxxxxx"))) //Masquer le bouton Tester
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }
    tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>' +
      '  </td>' +
      '</tr>';

    var row = appendCmdRow(tr);
    if (!row) return;
    var active = document.querySelector('.li_eqLogic.active');
    jeedom.eqLogic.buildSelectCmd({
      id: active ? active.getAttribute('data-eqLogic_id') : null,
      filter: {
        type: 'i'
      },
      error: function (error) {
        jeedomUtils.showAlert({
          message: error.message,
          level: 'danger'
        });
      },
      success: function (result) {
        row.querySelector('.cmdAttr[data-l1key=value]')?.insertAdjacentHTML('beforeend', result);
        row.setJeeValues(_cmd, '.cmdAttr');
        jeedom.cmd.changeType(row, init(_cmd.subType));
      }
    });
  }
}
