function highlightDirectSpeechInPosts() {
  //button holen
  const $caption = $('.highlightcaption').first();
  const currentState = $caption.data('state'); // "on" oder "off"

  if (currentState === 'on') {
    $caption.data('state', 'off');
    $caption.text('highlight "abc"');
    $('.post_body').find('span.highlight-speach').contents().unwrap();
  }
  else {
    $caption.data('state', 'on');
    $caption.text('highlight "abc" OFF');

    const quoteRegex = /(„[^“]+“|“[^”]+”|"[^"]+"|«[^»]+»)/g;

    $('.post_body').each(function () {
      //spans entfernen
      $(this).find('span.highlight-speach').contents().unwrap();

      let body_str = $(this).html();
      body_str = body_str.replace(quoteRegex, '<span class="highlight-speach">$1</span>');

      // korrektur html tags
      body_str = body_str.replace(
        /(<span class="highlight-speach">)([^<>]*)(<[^>]+>+)([^<>]*)(<\/span>)/g,
        '$1$2</span>$3<span class="highlight-speach">$4$5'
      );

      $(this).html(body_str);
    });
  }
}
