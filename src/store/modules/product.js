// Used for generic state props that aren't model-specific

import * as types from "../mutation-types"

const state = {
  product: {},
  searchResultsLoaded: false,
  searchResults: {}
}

const getters = {
  product: state => state.product,
  searchResults: state => state.searchResults,
  searchResultsLoaded: state => state.searchResultsLoaded
}

const actions = {
  /**
   * Sets the currently viewed product
   * @param {product} the product object
   */
  setProduct ({ commit }, product ) {
    commit(types.SET_PRODUCT, product)
  },

  fetchProducts ({ commit }, pageNumber = 1) {
    commit(types.SET_SEARCH_RESULTS_LOADED, false)

    Axios.get("/wp-json/wp/v2/sell_media_item", {
      params: {
        per_page: sell_media.posts_per_page,
        page: pageNumber
      }
    })
    .then(res => {
      let searchResults = {
        results: res.data,
        totalPages: parseInt(res.headers["x-wp-totalpages"]),
        pageNumber: pageNumber
      }

      commit(types.SET_SEARCH_RESULTS, searchResults)
      commit(types.SET_SEARCH_RESULTS_LOADED, true)
    })
    .catch(res => {
      console.log(res);
    });
  },

  searchProducts ({ commit }, { search, search_type, page_number = 1}) {
    commit(types.SET_SEARCH_RESULTS_LOADED, false)
    Axios.get( '/wp-json/sell-media/v2/search', {
      params: {
        s: search,
        type: search_type,
        per_page: sell_media.posts_per_page,
        page: page_number
      }
    } )
    .then(( res ) => {
      let searchResults = {
        results: res.data,
        hasSearchResults: res.headers[ 'x-wp-total' ] ? res.headers[ 'x-wp-total' ] : 0,
        totalPages: parseInt(res.headers["x-wp-totalpages"]),
        pageNumber: page_number
      }

      commit(types.SET_SEARCH_RESULTS, searchResults)
      commit(types.SET_SEARCH_RESULTS_LOADED, true)
    })
    .catch( ( res ) => {
      console.log( res )
    })
  }
}

const mutations = {
  [types.SET_PRODUCT](state, product) {
    state.product = product
  },

  [types.SET_SEARCH_RESULTS](state, results) {
    state.searchResults = results
  },

  [types.SET_SEARCH_RESULTS_LOADED](state, loaded) {
    state.searchResultsLoaded = loaded
  }
}

export default {
  state,
  getters,
  actions,
  mutations
}