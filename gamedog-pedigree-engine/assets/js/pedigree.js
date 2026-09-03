/**
 * GameDog Pedigree Engine - lightweight front-end helpers.
 */
(function () {
  'use strict';

  function enhanceCommonAncestors(root) {
    if (!root) {
      return;
    }

    var nodes = root.querySelectorAll('.gd-node-inbred, [data-common-ancestor="1"]');
    for (var i = 0; i < nodes.length; i++) {
      nodes[i].setAttribute('title', nodes[i].getAttribute('title') || 'Common ancestor in this pedigree');
    }
  }

  function initSiblingsTabs(root) {
    if (!root) {
      return;
    }

    var buttons = root.querySelectorAll('.gd-tab-btn');
    var panels = root.querySelectorAll('.gd-tab-panel');

    function activate(key) {
      var i;
      for (i = 0; i < buttons.length; i++) {
        var isActive = buttons[i].getAttribute('data-gd-tab') === key;
        buttons[i].classList.toggle('gd-tab-active', isActive);
        buttons[i].setAttribute('aria-selected', isActive ? 'true' : 'false');
      }
      for (i = 0; i < panels.length; i++) {
        var match = panels[i].getAttribute('data-gd-panel') === key;
        panels[i].classList.toggle('gd-tab-panel-active', match);
      }
    }

    for (var i = 0; i < buttons.length; i++) {
      buttons[i].addEventListener('click', function () {
        activate(this.getAttribute('data-gd-tab'));
      });
    }
  }

  function init() {
    var i;

    var trees = document.querySelectorAll('.gd-pedigree-tree');
    for (i = 0; i < trees.length; i++) {
      enhanceCommonAncestors(trees[i]);
    }

    var tabs = document.querySelectorAll('.gd-siblings-tabs');
    for (i = 0; i < tabs.length; i++) {
      initSiblingsTabs(tabs[i]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
