import{u,o as r,e as m,b as s,h as a,w as o,F as c,H as f,t as _,f as p,a as t,n as w,g,m as b}from"./app.568238bc.js";import{A as y}from"./AuthenticationCard.d21017ca.js";import{_ as h}from"./AuthenticationCardLogo.cc9313ff.js";import{_ as x,a as k}from"./TextInput.d0417be9.js";import{_ as V}from"./InputLabel.b114ab44.js";import{_ as v}from"./PrimaryButton.6d34d6df.js";import"./_plugin-vue_export-helper.cdc0426e.js";const F=t("div",{class:"mb-4 text-sm text-gray-600"}," Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one. ",-1),N={key:0,class:"mb-4 font-medium text-sm text-green-600"},$=["onSubmit"],C={class:"flex items-center justify-end mt-4"},z={__name:"ForgotPassword",props:{status:String},setup(l){const e=u({email:""}),n=()=>{e.post(route("password.email"))};return(S,i)=>(r(),m(c,null,[s(a(f),{title:"Forgot Password"}),s(y,null,{logo:o(()=>[s(h)]),default:o(()=>[F,l.status?(r(),m("div",N,_(l.status),1)):p("",!0),t("form",{onSubmit:b(n,["prevent"])},[t("div",null,[s(V,{for:"email",value:"Email"}),s(x,{id:"email",modelValue:a(e).email,"onUpdate:modelValue":i[0]||(i[0]=d=>a(e).email=d),type:"email",class:"mt-1 block w-full",required:"",autofocus:""},null,8,["modelValue"]),s(k,{class:"mt-2",message:a(e).errors.email},null,8,["message"])]),t("div",C,[s(v,{class:w({"opacity-25":a(e).processing}),disabled:a(e).processing},{default:o(()=>[g(" Email Password Reset Link ")]),_:1},8,["class","disabled"])])],40,$)]),_:1})],64))}};export{z as default};
