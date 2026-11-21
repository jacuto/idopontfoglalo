const {test}=require('node:test'),assert=require('node:assert/strict'),vm=require('node:vm');
const {prepareClassicScript}=require('../tools/build-assets.cjs');
function execute(sources,bundled){const context=vm.createContext({trace:[]});if(bundled)vm.runInContext(sources.map(prepareClassicScript).join('\n;\n'),context);else for(const source of sources)vm.runInContext(source,context);return JSON.parse(JSON.stringify(context.trace));}
test('classic script boundaries preserve captured predecessors and declaration hoisting',()=>{
 const sources=[`trace.push(page()); function page(){return 'first'} const original=page;`,`trace.push(page());function page(){return 'second'} const second=page;page=function(){return original()+' wrapped'};trace.push(page());`,`trace.push(second(),page());`];assert.deepEqual(execute(sources,true),execute(sources,false));
});
test('recursion follows the replaceable global binding, not a named expression binding',()=>{
 const sources=[`function run(n){return n?run(n-1):'base'} const before=run;`,`run=function(n){return 'changed'};trace.push(before(1));`];assert.deepEqual(execute(sources,true),execute(sources,false));
});
test('async functions, generators and the last duplicate declaration are retained',()=>{
 const sources=[`function pick(){return 1} function pick(){return 2} async function load(){return pick()} function* sequence(){yield pick()} trace.push(pick(),sequence().next().value,load.constructor.name);`];assert.deepEqual(execute(sources,true),execute(sources,false));
});
test('a strict script is rejected rather than silently changing its scope',()=>{assert.throws(()=>prepareClassicScript(`'use strict'; function x(){}`),/strict-mode/);});
