import{_ as c}from"./AppLayout.e6151bcd.js";import p from"./DeleteUserForm.75ef475a.js";import l from"./LogoutOtherBrowserSessionsForm.10215133.js";import{S as s}from"./SectionBorder.bc575392.js";import f from"./TwoFactorAuthenticationForm.9ef4d0cb.js";import u from"./UpdatePasswordForm.8ef8c788.js";import _ from"./UpdateProfileInformationForm.a83a1449.js";import{o,c as d,w as n,a as i,e as r,b as t,f as a,F as h}from"./app.568238bc.js";import"./_plugin-vue_export-helper.cdc0426e.js";import"./DialogModal.9431a152.js";import"./SectionTitle.4836414c.js";import"./DangerButton.e7b29fbe.js";import"./TextInput.d0417be9.js";import"./SecondaryButton.38884aa4.js";import"./ActionMessage.ee3ccf4b.js";import"./PrimaryButton.6d34d6df.js";import"./InputLabel.b114ab44.js";import"./FormSection.0d8b62ee.js";const g=i("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Profile ",-1),$={class:"max-w-7xl mx-auto py-10 sm:px-6 lg:px-8"},w={key:0},k={key:1},y={key:2},z={__name:"Show",props:{confirmsTwoFactorAuthentication:Boolean,sessions:Array},setup(m){return(e,x)=>(o(),d(c,{title:"Profile"},{header:n(()=>[g]),default:n(()=>[i("div",null,[i("div",$,[e.$page.props.jetstream.canUpdateProfileInformation?(o(),r("div",w,[t(_,{user:e.$page.props.user},null,8,["user"]),t(s)])):a("",!0),e.$page.props.jetstream.canUpdatePassword?(o(),r("div",k,[t(u,{class:"mt-10 sm:mt-0"}),t(s)])):a("",!0),e.$page.props.jetstream.canManageTwoFactorAuthentication?(o(),r("div",y,[t(f,{"requires-confirmation":m.confirmsTwoFactorAuthentication,class:"mt-10 sm:mt-0"},null,8,["requires-confirmation"]),t(s)])):a("",!0),t(l,{sessions:m.sessions,class:"mt-10 sm:mt-0"},null,8,["sessions"]),e.$page.props.jetstream.hasAccountDeletionFeatures?(o(),r(h,{key:3},[t(s),t(p,{class:"mt-10 sm:mt-0"})],64)):a("",!0)])])]),_:1}))}};export{z as default};
