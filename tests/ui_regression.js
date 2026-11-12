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
  parent:null,self:null,document,fetch:async()=>({ok:true,json:async()=>({ok:true})})
};
context.window.document=document;
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
 await new Promise(resolve=>setTimeout(resolve,30));
 assert.match(clipboard,new RegExp('entry\\.php\\?id='+id+'#key=a1b2$'));
 assert.equal(button.disabled,false);
 assert(notices.some(x=>/已复制/.test(x.message)));
 console.log('PASS: secure random four-character password and share-copy with password fragment');
})().catch(e=>{console.error(e);process.exit(1)});
