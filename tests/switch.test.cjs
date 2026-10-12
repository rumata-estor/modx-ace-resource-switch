'use strict';
const fs=require('fs'), vm=require('vm'),assert=require('assert');
const path = require('path');
const plugin = fs.readFileSync(path.join(__dirname, '..', 'AceResourceEditor.plugin.php'), 'utf8');
const match = plugin.match(/\$js = <<<'JAVASCRIPT'\r?\n([\s\S]*?)\r?\nJAVASCRIPT;/);
if (!match) throw new Error('Embedded JavaScript not found in plugin');
const source = match[1].replace('__ACE_RESOURCE_CONFIG__', JSON.stringify({mime:'text/html',modxTags:1}));
const exact='<!-- top -->\n<!-- second -->\n<div>initial</div>';
function normalize(x){return x.replace(/-->\s*<!--/g,'--><!--').replace(/\n(?=<div>)/g,'');}
function run(startRichText, delayedReady) {
 const textarea={name:'ta',el:{dom:{name:'ta',value:exact,style:{},removeAttribute(k){delete this[k]}}}};
 const hidden={value:exact,setValue(s){this.value=s}};
 const panel={rteLoaded:startRichText};
 let onCheck, warnings=[];
 const checkbox={state:startRichText,getValue(){return this.state},
    on(name,fn){assert.equal(name,'check');onCheck=fn},
    toggle(v){this.state=v;onCheck();}};
 const MODx={loadedRTEs:{},ux:{Ace:{}},onSaveEditor:undefined};
 MODx.loadRTE=function(){
   const events={};
   const editor={
     data:normalize(textarea.el.dom.value),
     status:delayedReady?'loading':'ready',
     isDirty:false,dirtySinceReset:false,
     on(name,fn){(events[name]||= []).push(fn)},
     emit(name){(events[name]||[]).forEach(fn=>fn())},
     resetDirty(){this.isDirty=false;this.dirtySinceReset=false},
     checkDirty(){return this.isDirty},
     getData(){return this.data},
     edit(s){this.data=normalize(s);this.isDirty=true;this.emit('change')},
     // Fire the editor's async ready signal after the plugin has installed hooks.
     ready(){this.status='ready';this.emit('instanceReady')}
   };
   MODx.loadedRTEs.ta={editor};
   textarea.el.dom.style.display='none';
   return true;
 };
 MODx.unloadRTE=function(){
   const current=this.loadedRTEs.ta;
   if(current)textarea.el.dom.value=current.editor.getData();
   delete this.loadedRTEs.ta;
   textarea.el.dom.style.display='';
 };
 if(startRichText)MODx.loadRTE();
 MODx.ux.Ace.replaceComponent=function(){
   const aceEditor={
     valueHolder:{name:'ta',value:textarea.el.dom.value},visible:true,
     el:{setDisplayed(state){aceEditor.visible=state}},
     editor:{resize(){}},
     setValue(s){this.valueHolder.value=s},
     getValue(){return this.valueHolder.value}
   };
   textarea.aceEditor=aceEditor;
   textarea.el.dom.removeAttribute('name');
   textarea.el.dom.style.display='none';
   MODx.onSaveEditor=function(fld){if(fld.aceEditor)fld.setValue(fld.aceEditor.getValue());};
 };
 const Ext={getCmp(id){return {
   'modx-panel-resource':panel,'ta':textarea,
   'modx-resource-richtext':checkbox,'hiddenContent':hidden}[id]},
   onReady(fn){fn()},
   Msg:{alert(title,msg){warnings.push({title,msg})}}
 };
 textarea.setValue=s=>{textarea.el.dom.value=s};
 vm.runInNewContext(source,{Ext,MODx,window:{MODx},setTimeout,console});
 const ready=()=>{if(delayedReady&&MODx.loadedRTEs.ta?.editor.status==='loading')MODx.loadedRTEs.ta.editor.ready()};
 function save() {
   hidden.setValue(textarea.el.dom.value);
   if(MODx.onSaveEditor)MODx.onSaveEditor(textarea);
   return hidden.value;
 }
 // Both startup modes must keep exact source after the initial visual-editor load.
 if(!startRichText)checkbox.toggle(true);
 ready();
 assert.notEqual(MODx.loadedRTEs.ta.editor.getData(),exact,'test requires CKEditor normalization');
 assert.equal(save(),exact,'no-edits save in CKEditor must be byte exact');
 checkbox.toggle(false);
 assert.equal(textarea.aceEditor.getValue(),exact,'no-edits return to Ace must be byte exact');
 assert.equal(save(),exact,'Ace save must be exact');
 assert.equal(warnings.length,0);
 // Edit in Ace, switch to CKEditor and back without touching visual editor.
 const changedAce='<!-- ACE edit -->\n'+exact;
 textarea.aceEditor.setValue(changedAce);
 checkbox.toggle(true);
 ready();
 assert.notEqual(MODx.loadedRTEs.ta.editor.getData(),changedAce);
 assert.equal(save(),changedAce,'even save in CKEditor must preserve prior Ace source if untouched');
 checkbox.toggle(false);
 assert.equal(textarea.aceEditor.getValue(),changedAce,'retain Ace formatting across roundtrip');
 assert.equal(save(),changedAce);
 assert.equal(warnings.length,0);
 // CKEditor edited: use serialized changed HTML and issue exactly one warning.
 checkbox.toggle(true);
 ready();
 const visual='<p>Visual edit</p>\n<!-- visual comment -->';
 MODx.loadedRTEs.ta.editor.edit(visual);
 assert.equal(save(),MODx.loadedRTEs.ta.editor.getData());
 checkbox.toggle(false);
 assert.equal(textarea.aceEditor.getValue(),normalize(visual),'visually edited HTML must win');
 assert.equal(warnings.length,1,'only visual edits warn about possible formatting changes');
 assert.equal(save(),normalize(visual));
 // A second visual edit does not spam warnings.
 checkbox.toggle(true);ready();
 MODx.loadedRTEs.ta.editor.edit('<p>More changes</p>');
 checkbox.toggle(false);
 assert.equal(warnings.length,1);
 assert.equal(textarea.aceEditor.getValue(),'<p>More changes</p>');
 console.log('PASS: initial CKEditor='+startRichText+', async ready='+delayedReady+', exact source, two modes saving, visual changes and one warning');
}
run(false,false);run(true,false);run(false,true);run(true,true);
