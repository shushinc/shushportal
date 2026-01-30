/******/ (() => { // webpackBootstrap
/******/ 	// The require scope
/******/ 	var __webpack_require__ = {};
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other entry modules.
(() => {
/*!**********************!*\
  !*** ./js/script.js ***!
  \**********************/
(function ($, Drupal) {

  Drupal.behaviors.custom = {
    attach: function (context, settings) {
      if($('.apigee-edge--form').length) {
        $('body').addClass('apps-create-from');
      }
      if($('.zcs-client-management-user-edit-form').length) {
        $('body').addClass('apps-create-from');
      }
      if($('#block-shush-main-menu .menu-expand').length) {
        $('#block-shush-main-menu ul li a.menu-expand').each(function(){
          $(this).parent().addClass('menu-active');
        })
      }
      $('.user-profile-name').each(function(){
        $(this).html($(this).text().substr(0, 1));
      })
      $('.user-profile-dropdown').each(function(){
        $(this).html($(this).text().substr(0, 1));
      })
      $(".user-profile-name").unbind().on( "click", function(event) {
        $(this).next('.user__profile').toggle();
      });
      $(".zcs-aws-app-list table tr td.api-keys .pwd-toggle").unbind().on("click", function(event) {
        $(this).parent().toggleClass('password-show');
        $('.zcs-aws-app-list table tr td').not(this).parent().removeClass('password-show');
      });

      $(".zcs-kong-app-list table tr td.api-keys .pwd-toggle").unbind().on("click", function(event) {
        $(this).parent().toggleClass('password-show');
        $('.zcs-kong-app-list table tr td').not(this).parent().removeClass('password-show');
      });
      

      //  Password copy
      
      $(".pwd-copy").unbind().click(function () {
        const copiedtext = $(this).closest("tr").find(".kong-key").text();
    
        if (navigator.clipboard) {
            // Use Clipboard API
          navigator.clipboard.writeText(copiedtext)
            .then(() => {
              alert('Text copied to clipboard successfully!');
            })
            .catch((error) => {
              console.error('Failed to copy text: ', error);
            });
        } else {
          // Fallback for browsers without Clipboard API
          const textArea = document.createElement("textarea");
          textArea.value = copiedtext;
          document.body.appendChild(textArea);
          textArea.select();
          
          try {
            document.execCommand("copy");
            alert('Text copied to clipboard successfully!');
          } catch (err) {
            console.error('Failed to copy text: ', err);
          } finally {
            document.body.removeChild(textArea);
          }
        }
      });

      $(".secret-password").unbind().click(function () {
        console.log('sec click');
        const copiedtext = $(this).closest("tr").find(".secret-key").text();
    
        if (navigator.clipboard) {
            // Use Clipboard API
          navigator.clipboard.writeText(copiedtext)
            .then(() => {
              alert('Text copied to clipboard successfully!');
            })
            .catch((error) => {
              console.error('Failed to copy text: ', error);
            });
        } else {
          // Fallback for browsers without Clipboard API
          const textArea = document.createElement("textarea");
          textArea.value = copiedtext;
          document.body.appendChild(textArea);
          textArea.select();
          
          try {
            document.execCommand("copy");
            alert('Text copied to clipboard successfully!');
          } catch (err) {
            console.error('Failed to copy text: ', err);
          } finally {
            document.body.removeChild(textArea);
          }
        }
      });

      $(".client-password").unbind().click(function () {
        console.log('cli click');
        const copiedtext = $(this).closest("tr").find(".client-key").text();
    
        if (navigator.clipboard) {
            // Use Clipboard API
          navigator.clipboard.writeText(copiedtext)
            .then(() => {
              alert('Text copied to clipboard successfully!');
            })
            .catch((error) => {
              console.error('Failed to copy text: ', error);
            });
        } else {
          // Fallback for browsers without Clipboard API
          const textArea = document.createElement("textarea");
          textArea.value = copiedtext;
          document.body.appendChild(textArea);
          textArea.select();
          
          try {
            document.execCommand("copy");
            alert('Text copied to clipboard successfully!');
          } catch (err) {
            console.error('Failed to copy text: ', err);
          } finally {
            document.body.removeChild(textArea);
          }
        }
      });
    

      $('.zcs-kong-app-list table tr').each(function() {
        var lastTd = $(this).find('td.app-operations');
        var lastTdLinks = lastTd.find('a');

        // Check if the links are already wrapped inside the desired div
        if (lastTd.find('.kong-app-list-edit').length === 0 && lastTdLinks.length > 1) {
          lastTdLinks.wrapAll('<div class="kong-app-list-edit"/>');
        }

      });
      $(".zcs-kong-app-list table tr td:last-child").unbind().on("click", function(event) {
        $(this).toggleClass('active-dropdown');
        $('.zcs-kong-app-list table tr td').not(this).removeClass('active-dropdown');
      });
      setTimeout(function() {
        $('.highlighted .messages--status').slideUp();
      }, 4000);
      $('.home-slider').slick({
        dots: true,
        arrows: false,
        slidesToShow: 1,
        slidesToScroll: 1,
        autoplay: true,
        autoplaySpeed: 5000,
      });
      // $('.client-Layout-column-wrapper select.country').select2({
      //   dropdownParent: $('.client-Layout-column-wrapper'),
      //   minimumResultsForSearch: 0,
      // });
      
    }
  };
})(jQuery, Drupal);

jQuery(document).ready(function($){

  // Toggle child menu visibility when the parent menu is clicked
  $('.site__menu .menu-item--expanded > span').on('click', function (e) {
    e.preventDefault();
    
    const $parentMenu = $(this).closest('.menu-item--expanded');
    
    const $childMenu = $parentMenu.children('ul.menu');
    
    const isExpanded = $parentMenu.hasClass('menuexpand');

    // Collapse other menus
    $('.site__menu .menu-item--expanded').not($parentMenu)
      .removeClass('menuexpand')
      .children('ul.menu').slideUp();

    if (!isExpanded) {
      $childMenu.stop(true, true).slideDown();
      $parentMenu.addClass('menuexpand');
    } else {
      $childMenu.stop(true, true).slideUp();
      $parentMenu.removeClass('menuexpand');
    }
  });
  

  $('.anchor-dropdown-btn').click(function(e) {
    e.preventDefault();
  });
  $('.login .header-site-logo').insertBefore('main .highlighted');
  const wordsToWrap = ['CAMARA', 'TS.43'];
  const regex = new RegExp(`\\b(${wordsToWrap.join('|')})\\b`, 'g');

  function wrapWordsInTextNode(node) {
    const parent = node.parentNode;

    // Skip if already inside a <strong class="text-bold">
    if (parent && parent.matches('strong.text-bold')) return;

    const text = node.textContent;
    if (!regex.test(text)) return;

    // Replace the matched text with <strong> elements
    const newHTML = text.replace(regex, '<strong class="text-bold">$1</strong>');

    // Replace the text node with new HTML
    const temp = document.createElement('span');
    temp.innerHTML = newHTML;
    parent.replaceChild(temp, node);
  }

  function processTables(context = document) {
    const tables = context.querySelectorAll('table.attributes-table');

    tables.forEach(table => {
      const cells = table.getElementsByTagName('td');
      for (let i = 0; i < cells.length; i++) {
        const cell = cells[i];

        const walker = document.createTreeWalker(cell, NodeFilter.SHOW_TEXT, null, false);
        const textNodes = [];
        while (walker.nextNode()) {
          textNodes.push(walker.currentNode);
        }

        textNodes.forEach(wrapWordsInTextNode);
      }
    });
  }

  // Initial run
  processTables();

  // Observe for dynamically added tables
  const observer = new MutationObserver((mutationsList) => {
    for (const mutation of mutationsList) {
      for (const node of mutation.addedNodes) {
        if (node.nodeType === 1) {
          processTables(node);
        }
      }
    }
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true,
  });
});

})();

// This entry needs to be wrapped in an IIFE because it needs to be in strict mode.
(() => {
"use strict";
/*!*************************!*\
  !*** ./sass/style.scss ***!
  \*************************/
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin

})();

/******/ })()
;
//# sourceMappingURL=main.js.map