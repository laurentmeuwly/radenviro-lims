import{u as h,j as p,o as n,e as l,b as e,h as t,w as s,F as _,H as y,f as g,a as o,n as v,g as a,L as m,m as x}from"./app.568238bc.js";import{A as b}from"./AuthenticationCard.d21017ca.js";import{_ as k}from"./AuthenticationCardLogo.cc9313ff.js";import{_ as w}from"./PrimaryButton.6d34d6df.js";import"./_plugin-vue_export-helper.cdc0426e.js";const V=o("div",{class:"mb-4 text-sm text-gray-600"}," Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another. ",-1),E={key:0,class:"mb-4 font-medium text-sm text-green-600"},B=["onSubmit"],C={class:"mt-4 flex items-center justify-between"},H={__name:"VerifyEmail",props:{status:String},setup(c){const d=c,i=h(),u=()=>{i.post(route("verification.send"))},f=p(()=>d.status==="verification-link-sent");return(r,L)=>(n(),l(_,null,[e(t(y),{title:"Email Verification"}),e(b,null,{logo:s(()=>[e(k)]),default:s(()=>[V,t(f)?(n(),l("div",E," A new verification link has been sent to the email address you provided in your profile settings. ")):g("",!0),o("form",{onSubmit:x(u,["prevent"])},[o("div",C,[e(w,{class:v({"opacity-25":t(i).processing}),disabled:t(i).processing},{default:s(()=>[a(" Resend Verification Email ")]),_:1},8,["class","disabled"]),o("div",null,[e(t(m),{href:r.route("profile.show"),class:"underline text-sm text-gray-600 hover:text-gray-900"},{default:s(()=>[a(" Edit Profile")]),_:1},8,["href"]),e(t(m),{href:r.route("logout"),method:"post",as:"button",class:"underline text-sm text-gray-600 hover:text-gray-900 ml-2"},{default:s(()=>[a(" Log Out ")]),_:1},8,["href"])])])],40,B)]),_:1})],64))}};export{H as default};
