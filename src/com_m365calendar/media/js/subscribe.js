/**
 * @package     Joomla.Site
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Copy-to-clipboard for the feed link. ES module, no globals, no jQuery,
 * no inline handlers (works with a strict Content Security Policy).
 */

const text = (key, fallback) => {
  if (window.Joomla && typeof window.Joomla.Text === 'object') {
    const value = window.Joomla.Text._(key, fallback);
    if (value) {
      return value;
    }
  }
  return fallback;
};

const announce = (id, message) => {
  const status = document.querySelector(`[data-m365calendar-copy-status="${CSS.escape(id)}"]`);
  if (status) {
    status.textContent = '';
    window.setTimeout(() => { status.textContent = message; }, 50);
  }
};

const copy = async (button) => {
  const id = button.getAttribute('data-m365calendar-copy');
  const input = id ? document.getElementById(id) : null;

  if (!input) {
    return;
  }

  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(input.value);
    } else {
      input.select();
      document.execCommand('copy');
    }
    announce(id, text('COM_M365CALENDAR_SUBSCRIBE_COPIED', 'Copied'));
  } catch (e) {
    input.select();
    announce(id, text('COM_M365CALENDAR_SUBSCRIBE_COPY_FAILED', 'Press Ctrl+C to copy'));
  }
};

const init = () => {
  document.querySelectorAll('.m365calendar [data-m365calendar-copy]').forEach((button) => {
    button.addEventListener('click', () => { copy(button); });
  });

  // Select the whole link when the field gets focus, for manual copying.
  document.querySelectorAll('.m365calendar .m365calendar-subscribe-panel input[readonly]').forEach((input) => {
    input.addEventListener('focus', () => input.select());
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
