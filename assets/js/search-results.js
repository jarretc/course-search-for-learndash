jQuery(document).ready(function($) {
    var searchTimeout;

    $('#search_term').on('input', function(e) {
        e.preventDefault();

        clearTimeout(searchTimeout);
        
        var searchTerm = $('#search_term').val();
        
        $('#search-loading').show();
        $('#ajax-search-results').hide();

        if ( searchTerm.length === 0 ) {
            $('#ajax-search-results').hide();
            $('#search-loading').hide();
            return;
        }


        searchTimeout = setTimeout(function() {
            $.ajax({
                url: odd_cs_search.ajax_url,
                type: 'POST',
                data: {
                    action: 'odd_cs_search',
                    search_nonce: $('#search_nonce').val(),
                    search_term: searchTerm,
                    atts: $('#atts').val(),
                },
                success: function(response) {
                    $('#search-loading').hide();
                    if (response.success && searchTerm.length > 0) {
                        var html = '<strong>Search Results</strong>';

                        $.each(response.data.results, function(index, result) {
                            html += '<div class="search-result-item">';
                            html += '<a href="' + result.url + '">' + result.title + '</a>';
                            if ( result.excerpt.length > 0 ) {
                                html += '<div class="excerpt">' + result.excerpt + '</div>';
                            }
                            html += '</div>';
                        });
                        
                        $('#ajax-search-results').html(html).show();
                    } else {
                        $('#ajax-search-results').html(odd_cs_search.no_results).show();
                    }
                },
                error: function() {
                    $('#search-loading').hide();
                    $('#ajax-search-results').html(odd_cs_search.invalid_search).show();
                }
            });
        }, 500 );
    });
});
