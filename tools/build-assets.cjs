/* Build-time only. Production needs PHP and the committed artifacts, never Node.js. */
const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto'),zlib=require('node:zlib');
const acorn=require('acorn'),terser=require('terser'),CleanCSS=require('clean-css');
const root=path.resolve(__dirname,'..');
const digest=data=>crypto.createHash('sha256').update(data).digest('hex');
function prepareClassicScript(source){
 const ast=acorn.parse(source,{ecmaVersion:'latest',sourceType:'script'});
 if(ast.body.some(n=>n.directive==='use strict'))throw Error('Separate strict-mode scripts require a separate bundle.');
 const declarations=ast.body.filter(n=>n.type==='FunctionDeclaration'),last=new Map();
 for(const n of declarations)last.set(n.id.name,n);
 // A browser instantiates every top-level function at the beginning of EACH script.
 // Plain concatenation would hoist later replacements before earlier snapshots.
 // Initialize anonymous expressions at each original script boundary instead.
 const prelude=[...last.values()].map(n=>{
  const prefix=source.slice(n.start,n.id.start),suffix=source.slice(n.id.end,n.end);
  return 'var '+n.id.name+'='+prefix+suffix+';';
 }).join('\n');
 let body=source;for(const n of [...declarations].reverse())body=body.slice(0,n.start)+body.slice(n.end);
 return prelude+'\n'+body;
}
async function build({check=false}={}){
 const spec=JSON.parse(fs.readFileSync(path.join(root,'frontend.sources.json'),'utf8'));
 const scripts=spec.scripts.map(file=>({file,source:fs.readFileSync(path.join(root,'public',file),'utf8')}));
 const cssSources=spec.styles.map(file=>fs.readFileSync(path.join(root,'public',file),'utf8'));
 const joined=scripts.map(x=>'\n/* '+x.file+' */\n'+prepareClassicScript(x.source)).join('\n;\n');
 const js=await terser.minify(joined,{compress:false,mangle:false,ecma:2022,format:{comments:/@license|@preserve|Copyright|SPDX/}});
 if(!js.code)throw Error('Empty JavaScript build');
 const css=new CleanCSS({level:0,rebase:false,format:false}).minify(cssSources.join('\n'));
 if(css.errors.length)throw Error(css.errors.join('\n'));
 const jsContent='/* Ráérő application. Source order: frontend.sources.json. QRCode: MIT, Copyright (c) 2009 Kazuhiko Arase. */\n'+js.code+'\n';
 const cssContent=css.styles+'\n';
 const jsPath='build/app.'+digest(jsContent).slice(0,16)+'.js',cssPath='build/app.'+digest(cssContent).slice(0,16)+'.css';
 const inputs={};for(const file of [...spec.scripts,...spec.styles])inputs[file]=digest(fs.readFileSync(path.join(root,'public',file)));
 const manifest={version:1,script:jsPath,style:cssPath,inputs,metrics:{scriptCount:scripts.length,sourceScriptBytes:scripts.reduce((n,x)=>n+Buffer.byteLength(x.source),0),scriptBytes:Buffer.byteLength(jsContent),styleCount:spec.styles.length,sourceStyleBytes:cssSources.reduce((n,s)=>n+Buffer.byteLength(s),0),styleBytes:Buffer.byteLength(cssContent)}};
 manifest.gzip={script:jsPath+'.gz',style:cssPath+'.gz'};
 const outputs={[jsPath]:jsContent,[cssPath]:cssContent,[jsPath+'.gz']:zlib.gzipSync(jsContent,{level:9}),[cssPath+'.gz']:zlib.gzipSync(cssContent,{level:9}),'build/manifest.json':JSON.stringify(manifest,null,2)+'\n'};
 for(const [file,content]of Object.entries(outputs)){
  const full=path.join(root,'public',file);
  if(check){if(!fs.existsSync(full)||!fs.readFileSync(full).equals(Buffer.isBuffer(content)?content:Buffer.from(content)))throw Error('Stale asset: '+file+'; run npm run build');}
  else{fs.mkdirSync(path.dirname(full),{recursive:true});fs.writeFileSync(full,content);}
 }
 if(!check)for(const file of fs.readdirSync(path.join(root,'public/build'))){if(/^app\.[a-f0-9]{16}\.(js|css)(\.gz)?$/.test(file)&&!Object.hasOwn(outputs,'build/'+file))fs.unlinkSync(path.join(root,'public/build',file));}
 return manifest.metrics;
}
module.exports={prepareClassicScript,build};
if(require.main===module)build({check:process.argv.includes('--check')}).then(metrics=>console.log(JSON.stringify(metrics))).catch(e=>{console.error(e);process.exitCode=1;});
