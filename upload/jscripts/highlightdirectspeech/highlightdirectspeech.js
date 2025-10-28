function highlightDirectSpeechInPosts() {
  //button holen
  const $caption = $('.highlightcaption').first();
  const currentState = $caption.data('state'); // "on" oder "off"

  if (currentState === 'on') {
    $caption.data('state', 'off');
    $caption.text('highlight "abc"');
    $('.post_body').find('span.directspeech').contents().unwrap();
  }
  else {
    $caption.data('state', 'on');
    $caption.text('highlight "abc" OFF');

    const quoteRegex = /(„[^“]+“|“[^”]+”|"[^"]+"|«[^»]+»)/g;

    $('.post_body').each(function () {
      //spans entfernen
      $(this).find('span.directspeech').contents().unwrap();

      let body_str = $(this).html();
      body_str = body_str.replace(quoteRegex, '<span class="directspeech">$1</span>');

      // korrektur html tags
      body_str = body_str.replace(
        /(<span class="directspeech">)([^<>]*)(<[^>]+>+)([^<>]*)(<\/span>)/g,
        '$1$2</span>$3<span class="directspeech">$4$5'
      );

      $(this).html(body_str);
    });
  }
}