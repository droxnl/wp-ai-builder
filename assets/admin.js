jQuery(function ($) {
  function collectBrief() {
    return {
      action: null,
      nonce: wpAiBuilder.nonce,
      sector: $('#wp_ai_builder_sector').val(),
      logo: $('#wp_ai_builder_logo').val(),
      colors: $('#wp_ai_builder_colors').val(),
      siteType: $('#wp_ai_builder_site_type').val(),
      pages: $('#wp_ai_builder_pages').val(),
      notes: $('#wp_ai_builder_notes').val(),
    };
  }

  function setStatus(message, isError) {
    $('#wp-ai-builder-status')
      .text(message)
      .css('color', isError ? '#b91c1c' : '#0f172a');
  }

  $('#wp-ai-builder-preview').on('click', function (event) {
    event.preventDefault();
    setStatus('Generating preview...', false);

    const data = collectBrief();
    data.action = 'wp_ai_builder_preview';

    $.post(wpAiBuilder.ajaxUrl, data)
      .done(function (response) {
        if (!response.success) {
          setStatus(response.data.message || 'Preview failed.', true);
          return;
        }
        $('#wp-ai-builder-preview').html(response.data.html);
        setStatus('Preview updated.', false);
      })
      .fail(function () {
        setStatus('Preview failed. Please try again.', true);
      });
  });

  $('#wp-ai-builder-build').on('click', function (event) {
    event.preventDefault();
    setStatus('Building site...', false);

    const data = collectBrief();
    data.action = 'wp_ai_builder_build';

    $.post(wpAiBuilder.ajaxUrl, data)
      .done(function (response) {
        if (!response.success) {
          setStatus(response.data.message || 'Build failed.', true);
          return;
        }
        setStatus('Site created. Pages: ' + response.data.pages.join(', '), false);
      })
      .fail(function () {
        setStatus('Build failed. Please try again.', true);
      });
  });
});
