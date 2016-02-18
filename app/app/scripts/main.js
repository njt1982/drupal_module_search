/*global jQuery:false Handlebars:false algoliasearch:false */

(function($, Handlebars, algoliasearch) {
  'use strict';
  var app = {
    client: null,
    index: null,
    searchBox: null,
    searchResultsContainer: null,
    searchResultTemplate: null,

    init: function() {
      this.client = algoliasearch('A644RMPSD6', '392ee956e285f537ee958f9f395c0253');
      this.index = this.client.initIndex('prod_drupal_modules');

      this.searchResultTemplate = Handlebars.compile($('#searchResultTemplate').html());
      this.searchResultsContainer = $('#searchResults');

      this.searchBox = $('#searchbox');

      this.searchBox.on('keyup', function(e) {
        if (e.target.value !== '') {
          app.index.search(e.target.value)
            .then(function searchSuccess(content) {
              app.searchResultsContainer.empty();

              $.each(content.hits, function(i, item) {
                app.searchResultsContainer.append(
                  app.searchResultTemplate(item)
                );
              });
            })
            .catch(function searchError(err) {
              console.error(err);
            });
        }
        else {
          app.searchResultsContainer.empty();
        }
      });
    }
  };

  app.init();

})(jQuery, Handlebars, algoliasearch);
