/**
 * GameDog Pedigree Engine — lightweight front-end helpers.
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

  function init() {
    var trees = document.querySelectorAll('.gd-pedigree-tree');
    for (var i = 0; i < trees.length; i++) {
      enhanceCommonAncestors(trees[i]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
