/** Zero-dependency UI behavior smoke test; run with node tests/ui_regression.js. */
const fs = require('fs');
const vm = require('vm');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const {URL, FormData, Event} = global;
let clipboard = '';
let notices = [];
const pwdInput = {value:'', type:'password',focus(){},select(){},dispatchEvent(){}};
const passwordMode={value:'keep'};
const document = {
  getElementById(id){return id==='publish-access-password'||id==='entry-new-password'?pwdInput:null;},
  querySelector(sel){return sel==='select[name="password_mode"]'?passwordMode:null;},
};
const context={
  console,URL,FormData,Event,Uint32Array,setTimeout,clearTimeout,Promise,
  navigator:{clipboard:{writeText: async function (text){clipboard=text;}}},
  window:{crypto:crypto.webcrypto,isSecureContext:true,location:{href:'https://example.test/entry.php'}},
  parent:null,self:null,document,fetch:async(url,opts)=>({ok:opts.body.get('action')!=='share_recover',json:async()=>opts.body.get('action')==='share_recover'?{ok:false,code:'needs_password'}:{ok:true}})
};
context.window.document=document;
context.window.fetch=context.fetch;
context.window.FormData=FormData;
context.parent=context.self=context.window;
vm.createContext(context);
vm.runInContext(fs.readFileSync(require('node:path').join(__dirname,'../inc/main.js'),'utf8'),context);
const main=context.main;
main.notify=function(message,error){notices.push({message,error})};
(async function(){
 for(let i=0;i<20;i++) {
   main.generateAccessPassword('publish-access-password');
   assert.match(pwdInput.value,/^[a-z0-9]{4}$/);
   assert.match(pwdInput.value,/[a-z]/);
   assert.match(pwdInput.value,/[0-9]/);
 }
 main.generateAccessPassword('entry-new-password');
 assert.equal(passwordMode.value,'set');
 const id='0123456789abcdef01234567';
 const button={disabled:false,getAttribute(k){return {
   'data-share-url':'entry.php?id='+id,
   'data-share-protected':'1',
   'data-share-csrf':'csrf-test'
 }[k]}};
 context.window.prompt=function(){return 'a1b2'};
 main.shareEntry(button);
 await new Promise(resolve=>setTimeout(resolve,80));
 assert.match(clipboard,new RegExp('entry\\.php\\?id='+id+'#key=a1b2$'));
 assert.equal(button.disabled,false);
 // One-click share on the browser that published/unlocked the content.
 let promptCalls=0,fetchCalls=0;
 context.window.prompt=function(){promptCalls++;return 'should-not-be-used';};
 context.fetch=async()=>{fetchCalls++;throw Error('Direct share must not use network');};
 const direct={disabled:false,getAttribute(k){return {
   'data-share-url':'entry.php?id='+id,
   'data-share-protected':'1',
   'data-share-ready':'entry.php?id='+id+'#key=ab13'
 }[k]}};
 main.shareEntry(direct);
 await new Promise(resolve=>setTimeout(resolve,10));
 assert.match(clipboard,/#key=ab13$/);
 assert.equal(promptCalls,0);
 assert.equal(fetchCalls,0);


 // Edit/publish expiry selectors must be activated only for custom expiry.
 const date = {name:'expiry_date',disabled:true,required:false};
 const hour = {name:'expiry_hour',disabled:true};
 const minute = {name:'expiry_minute',disabled:true};
 const select={value:'keep',listener:null,addEventListener(event,fn){this.listener=fn;}};
 const panel={hidden:true,querySelectorAll(){return [date,hour,minute];}};
 const prevGet=document.getElementById;
 document.getElementById=(id)=>id==='expiry-choice-test'?select:id==='expiry-panel-test'?panel:prevGet(id);
 main.setupExpiryPicker('expiry-choice-test','expiry-panel-test');
 assert(panel.hidden&&date.disabled&&!date.required);
 select.value='custom';select.listener();
 assert(!panel.hidden&&!date.disabled&&!hour.disabled&&!minute.disabled&&date.required);
 select.value='keep';select.listener();
 assert(panel.hidden&&date.disabled&&!date.required);
 document.getElementById=prevGet;

 assert(notices.some(x=>/已复制/.test(x.message)));
 console.log('PASS: secure random four-character password; direct same-browser share without password prompt; fallback verification');
})().catch(e=>{console.error(e);process.exit(1)});
