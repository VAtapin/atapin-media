(() => {
  const instances = new WeakMap();
  const selector = 'textarea[data-rich-text]';
  const language = () => document.documentElement.lang?.toLowerCase().startsWith('de') ? 'de' : 'en';

  const options = textarea => ({
    language: language(),
    minHeight: Number(textarea.dataset.richTextHeight || 240),
    toolbarAdaptive: true,
    toolbarSticky: false,
    spellcheck: true,
    beautifyHTML: false,
    askBeforePasteHTML: false,
    askBeforePasteFromWord: false,
    defaultActionOnPaste: 'insert_clear_html',
    defaultActionOnPasteFromWord: 'insert_clear_html',
    uploader: {insertImageAsBase64URI: false},
    buttons: [
      'undo','redo','|','paragraph','|','bold','italic','underline','strikethrough','|',
      'ul','ol','outdent','indent','|','align','blockquote','link','table','hr','|',
      'eraser','source','fullsize',
    ],
    buttonsXS: ['undo','redo','bold','italic','underline','ul','ol','paragraph','link','table','source','fullsize'],
    placeholder: textarea.placeholder || '',
  });

  const syncOne = textarea => {
    const editor = instances.get(textarea);
    if (editor && textarea.value !== editor.value) textarea.value = editor.value;
    return textarea.value;
  };

  const attach = textarea => {
    if (!(textarea instanceof HTMLTextAreaElement) || instances.has(textarea) || !window.Jodit) return instances.get(textarea) || null;
    try {
      const editor = window.Jodit.make(textarea, options(textarea));
      instances.set(textarea, editor);
      textarea.dataset.richTextReady = 'true';
      editor.events.on('change', value => {
        textarea.value = typeof value === 'string' ? value : editor.value;
        textarea.dispatchEvent(new Event('input', {bubbles:true}));
      });
      editor.events.on('blur', () => syncOne(textarea));
      return editor;
    } catch (error) {
      textarea.dataset.richTextError = 'true';
      return null;
    }
  };

  const attachAll = (root = document) => {
    if (root.matches?.(selector)) attach(root);
    root.querySelectorAll?.(selector).forEach(attach);
  };
  const sync = (root = document) => root.querySelectorAll?.(selector).forEach(syncOne);
  const setValue = (textarea, value = '') => {
    textarea.value = value;
    const editor = instances.get(textarea) || attach(textarea);
    if (editor && editor.value !== value) editor.value = value;
  };
  const getValue = textarea => syncOne(textarea);

  window.DesktopRichText = {attach, attachAll, sync, setValue, getValue};
  const start = () => {
    attachAll();
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
      if (node.nodeType === Node.ELEMENT_NODE) attachAll(node);
    }))).observe(document.body, {childList:true, subtree:true});
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true}); else start();
})();
