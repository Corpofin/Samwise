var Vue = require('vue')
var Vuex = require('vuex')

Vue.use(Vuex)

const state = {
  status: {
    notifications: [],
    messages: 0,
    invoices: 0,
    events: 0
  },
  user: {}
}

const mutations = {
  SET_MESSAGES (state, count) {
    state.status.messages = count
  },

  SET_INVOICES (state, count) {
    state.status.invoices = count
  },

  SET_EVENTS (state, count) {
    state.status.events = count
  },

  LOGIN (state, user) {
    state.user = user
  },

  LOGOUT (state) {
    state.user = {}
  }
}

module.exports = new Vuex.Store({
  state,
  mutations
})