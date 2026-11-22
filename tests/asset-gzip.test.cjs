const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),zlib=require('node:zlib');
const root=path.resolve(__dirname,'../public'),manifest=JSON.parse(fs.readFileSync(path.join(root,'build/manifest.json'),'utf8'));
for(const kind of ['script','style'])test('precompressed '+kind+' decodes exactly to the unchanged public source',()=>{
 const source=fs.readFileSync(path.join(root,manifest[kind])),compressed=fs.readFileSync(path.join(root,manifest.gzip[kind]));
 assert.equal(manifest.gzip[kind],manifest[kind]+'.gz');assert.deepEqual(zlib.gunzipSync(compressed),source);assert.ok(compressed.length<source.length*.5);assert.deepEqual(zlib.gzipSync(source,{level:9}),compressed);
});
