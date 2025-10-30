function highlightDirectSpeechInPosts() {
  const $caption = $('.highlightcaption').first();
  const isOn = $caption.data('state') === 'on';
  const $posts = $('.post_body');

  $caption.data('state', isOn ? 'off' : 'on');
  $caption.text(isOn ? 'highlight "abc"' : 'highlight "abc" OFF');

  // Alte Hervorhebungen entfernen
  $posts.find('span.directspeech').contents().unwrap();
  if (isOn) return;

  const quoteRegex = /(„[^“]+“|“[^”]+”|"[^"]+"|«[^»]+»)/g;

  // Klassen, die ignoriert werden sollen
  const excludeClasses = [
    'editreason',
    'moderator_notice'
  ];

  // ID-Präfixe, die ignoriert werden sollen
  const excludeIdPrefixes = [
    'notemoderator_post_'
  ];

  $posts.each(function () {
    const walker = document.createTreeWalker(this, NodeFilter.SHOW_TEXT);
    const textNodes = [];

    while (walker.nextNode()) {
      const node = walker.currentNode;
      let parent = node.parentNode;
      let skip = false;

      while (parent && parent !== this) {
        if (parent.nodeType === 1) { // ELEMENT_NODE
          // Klassen prüfen
          for (const cls of excludeClasses) {
            if (parent.classList && parent.classList.contains(cls)) {
              skip = true;
              break;
            }
          }

          // IDs prüfen
          for (const prefix of excludeIdPrefixes) {
            if (parent.id && parent.id.startsWith(prefix)) {
              skip = true;
              break;
            }
          }
        }

        if (skip) break;
        parent = parent.parentNode;
      }

      if (!skip) textNodes.push(node);
    }

    textNodes.forEach(node => {
      const text = node.nodeValue;
      if (!quoteRegex.test(text)) return;
      quoteRegex.lastIndex = 0;

      const replaced = text.replace(quoteRegex, '<span class="directspeech">$1</span>');
      const temp = document.createElement('span');
      temp.innerHTML = replaced;
      node.replaceWith(...temp.childNodes);
    });
  });
}
