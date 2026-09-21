(function (Drupal, once) {
  // Show/Reset sit inline beside the (masked) key input.
  const style = document.createElement('style');
  style.textContent = '.hashed-key-toggle, .hashed-key-reset { margin-inline-start: 8px; }';
  document.head.appendChild(style);

  Drupal.behaviors.hashedKeyToggle = {
    attach(context) {
      
    },
  };
})(Drupal, once);
