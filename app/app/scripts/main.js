/*global jQuery:false Handlebars:false algoliasearch:false algoliasearchHelper:false */

(function($, Handlebars, algoliasearch) {
  'use strict';
  var app = {
    client: null,
    helper: null,

    searchBox: null,

    searchResultsContainer: null,
    searchFacetsContainer: null,

    searchResultTemplate: null,
    searchFacetTemplate: null,

    facetMap: {
      author: 'Authors',
      compatibility: 'Compatibility',
      category: 'Categories',
      maintenance_status: 'Maintenance Status',
      development_status: 'Development Status',
      project_type: 'Project Type'
    },

    init: function() {
      this.client = algoliasearch('A644RMPSD6', '392ee956e285f537ee958f9f395c0253');
      this.helper = algoliasearchHelper(this.client, 'prod_drupal_modules', {
        facets: Object.keys(this.facetMap)
      });
      this.helper.on('result', this.handleResults);

      this.searchResultTemplate = Handlebars.compile($('#searchResultTemplate').html());
      this.searchFacetTemplate = Handlebars.compile($('#searchFacetTemplate').html());
      this.searchSummaryTemplate = Handlebars.compile($('#searchSummaryTemplate').html());

      this.searchResultsContainer = $('#searchResults');
      this.searchFacetsContainer = $('#searchFacets');
      this.searchSummaryContainer = $('#searchSummary');

      this.searchFacetsContainer.on('click', '[data-facet-key]', function(e) {
        e.stopPropagation();
        var $item = $(e.currentTarget);
        var key = $item.data('facet-key');
        var value = $item.data('facet-value');
        app.helper.setPage(0);
        app.helper.toggleRefine(key, value).search();
      });

      this.searchBox = $('#searchbox');

      this.searchBox.on('keyup', function(e) {
        if (e.currentTarget.value !== '') {
          app.helper.setQuery(e.currentTarget.value).search();
          app.eventTrack('searchbox', 'search', e.currentTarget.value)
        }
        else {
          app.searchResultsContainer.empty();
        }
      });
    },

    eventTrack: function(category, action, label, value) {
      if (typeof(ga) !== 'undefined') {
        ga('send', 'event', category, action, label, value);
      }
    },

    handleResults: function(results) {
      app.searchResultsContainer.empty();
      app.searchFacetsContainer.empty();

      app.searchSummaryContainer.html(
        app.searchSummaryTemplate({
          hits: results.nbHits,
          time: results.processingTimeMS
        })
      );

      $.each(results.hits, function(i, item) {
        app.searchResultsContainer.append(
          app.searchResultTemplate(item)
        );
      });

      $.each(results.facets, function(i, facet) {
        var data = {
          key: facet.name,
          name: app.facetMap[facet.name],
          items: [],
          isRefined: false
        };
        for (var facetName in facet.data) {
          var row = {
            name: facetName,
            count: facet.data[facetName],
            refined: app.helper.isRefined(facet.name, facetName)
          };
          data.isRefined |= row.refined;

          data.items.push(row);
        }
        app.searchFacetsContainer.append(
          app.searchFacetTemplate(data)
        );
      });

      app.searchFacetsContainer.collapse();
    }
  };

  app.init();

  $('#menu-toggle').click(function(e) {
    e.preventDefault();
    $('#wrapper').toggleClass('toggled');
  });
})(jQuery, Handlebars, algoliasearch);
