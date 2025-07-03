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

      $(".zcs-kong-app-list table tr td.api-keys .pwd-toggle").unbind().on("click", function(event) {
        $(this).parent().toggleClass('password-show');
        $('.zcs-kong-app-list table tr td').not(this).parent().removeClass('password-show');
      });
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
      $(".site__menu ul.menu .menu-item--expanded").unbind().on( "click", function(event) {
        $(this).toggleClass('menuexpand');
      });
      $(document).ready(function() {
        $('.client-Layout-column-wrapper select').select2({
          dropdownCssClass: "custom-scroll" // optional custom styling
        });
      });
    }
  };
})(jQuery, Drupal);
jQuery(document).ready(function($){
  // Add the 'parent-menu' class to <li> elements that have a <ul> (i.e., parent menus)
  $('li:has(ul)').addClass('parent-menu');
  // Add the 'child-menu' class to <ul> elements that are inside a parent <li>
  $('li:has(ul) > ul').addClass('child-menu');
  // Initially hide all child menus
  $('.child-menu').hide();
  // Show child-menu if it contains an active link
  $('.child-menu').has('a.is-active').show().closest('.parent-menu').addClass('menuexpand');
  // Toggle child menu visibility when the parent menu is clicked
  $('.parent-menu > span').click(function() {
    var $parentMenu = $(this).parent('.parent-menu');
    var $childMenu = $(this).next('.child-menu');
  
    // Collapse all other menus
    $('.parent-menu').not($parentMenu).removeClass('menuexpand');
    $('.parent-menu .child-menu').not($childMenu).slideUp();
  
    // Toggle clicked menu
    $childMenu.stop(true, true).slideToggle();
    $parentMenu.toggleClass('menuexpand');
  });

  $('.anchor-dropdown-btn').click(function(e) {
    e.preventDefault();
  });
  $('.login .header-site-logo').insertBefore('main .highlighted');
});
