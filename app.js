// CheckNGo — shared app-wide JS

document.addEventListener('DOMContentLoaded', () => {
  // Fade-in any element flagged for entrance animation.
  document.querySelectorAll('.fade-in-up').forEach((el, i) => {
    el.style.animationDelay = `${i * 40}ms`;
  });
});

/**
 * Wraps SweetAlert2 confirm dialogs with CheckNGo's dark-glass theme.
 * Usage: ckConfirm('Confirm Departure?', 'Some items are still unchecked.').then(ok => ...)
 */
function ckConfirm(title, text, confirmText = 'Confirm') {
  return Swal.fire({
    title,
    text,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: confirmText,
    cancelButtonText: 'Cancel',
    background: '#242424',
    color: '#F8F8F8',
    confirmButtonColor: '#F8F8F8',
    cancelButtonColor: '#3a3a3a',
  }).then(r => r.isConfirmed);
}

function ckToast(message, icon = 'success') {
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon,
    title: message,
    showConfirmButton: false,
    timer: 2200,
    background: '#242424',
    color: '#F8F8F8',
  });
}
