webpackJsonp([3],{FS4i:function(t,s){},oZfa:function(t,s,a){"use strict";Object.defineProperty(s,"__esModule",{value:!0});var i={data:function(){return{list:[],id:"",page:1}},filters:{toFixeds:function(t){return(t=Number(t)).toFixed(4)}},created:function(){var t=window.localStorage.getItem("token")||"";t&&(this.token=t,this.id=this.$route.query.id||"",this.getList())},methods:{getList:function(){var t=this,s=arguments.length>0&&void 0!==arguments[0]&&arguments[0],a={};s||(this.page=1),a.seller_id=this.id,a.page=this.page;var i=layer.load(1);this.$http({url:"/api/seller/balance_log",params:a,headers:{Authorization:this.token}}).then(function(a){if(layer.close(i),"ok"==a.data.type){var e=a.data.message;s?e.data.length?t.list=t.list.concat(e.data):layer.msg(t.$t("td.nomore")):t.list=e.data,e.data.length&&(t.page+=1)}})}}},e={render:function(){var t=this,s=t.$createElement,a=t._self._c||s;return a("div",{staticClass:"whites",attrs:{id:"legal-record"}},[a("div",{staticClass:"flex alcenter"},[a("div",{staticClass:"title bgf8"},[t._v(t._s(t.$t("fat.journal")))]),t._v(" "),a("div",{staticClass:"title bgf8 ml25 blue pointer",on:{click:function(s){t.$router.push({path:"/legalShopDetail",query:{id:t.id}})}}},[t._v(t._s(t.$t("fat.myshops")))])]),t._v(" "),a("ul",{staticClass:"bgf8 mt60"},[a("li",{staticClass:"bod_bc bdb pdtb5"},[a("div",{staticClass:"flex li-b"},[a("div",{staticClass:"flex1"},[a("div",{staticClass:"tc ft14"},[t._v(t._s(t.$t("td.num")))])]),t._v(" "),a("div",{staticClass:"flex1"},[a("div",{staticClass:"tc ft14"},[t._v(t._s(t.$t("fat.type")))])]),t._v(" "),a("div",{staticClass:"flex1"},[a("div",{staticClass:"tc ft14"},[t._v(t._s(t.$t("td.time")))])]),t._v(" "),a("div",{staticClass:"flex1"},[a("div",{staticClass:"tc ft14"},[t._v(t._s(t.$t("fat.sd")))])])])]),t._v(" "),t._l(t.list,function(s,i){return a("li",{key:i,staticClass:"bod_bc bdb pdtb5"},[a("div",{staticClass:"flex li-b"},[a("div",{staticClass:"flex1"},[a("div",{staticClass:"tc"},[t._v(t._s(t._f("toFixeds")(s.value||"0.0000"))+" "+t._s(s.currency_name))])]),t._v(" "),a("div",{staticClass:"flex1"},[a("div",{staticClass:"tc"},[t._v(t._s(s.memo))])]),t._v(" "),a("div",{staticClass:"flex1"},[a("div",{staticClass:"tc"},[t._v(t._s(s.updated_at))])]),t._v(" "),a("div",{staticClass:"flex1"},[1==s.is_lock?a("div",{staticClass:"tc"},[t._v(t._s(t.$t("fat.yes")))]):a("div",{staticClass:"tc"},[t._v(t._s(t.$t("fat.no")))])])])])})],2),t._v(" "),t.list.length?a("div",{staticClass:"more",on:{click:function(s){t.getList(!0)}}},[t._v(t._s(t.$t("td.more")))]):a("div",{staticClass:"nomore"},[t._v(t._s(t.$t("td.nomore")))])])},staticRenderFns:[]};var l=a("VU/8")(i,e,!1,function(t){a("FS4i")},null,null);s.default=l.exports}});
//# sourceMappingURL=3.8316b8cc4bcdf717a3ce.js.map