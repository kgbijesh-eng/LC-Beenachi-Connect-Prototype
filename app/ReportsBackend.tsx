"use client";

import { useEffect, useState } from "react";

type Role = "president"|"secretary"|"treasurer"|"member";
type Definition = { key:string; title:string; description:string; allowed_filters:string[]; aggregate_only:boolean; sensitivity:string };
type Report = { key:string; title:string; description:string; generated_at:string; club_timezone:string; date_basis:string; applied_filters:Record<string,unknown>; summary:Record<string,unknown>; rows:Record<string,unknown>[] };
type Meta = { current_page:number; last_page:number; per_page:number; total:number };
const API="http://127.0.0.1:8000/api/v1";
const logins:Record<Role,{email:string;password:string}>={
  president:{email:"president@lcbeenachi.test",password:"password"},
  secretary:{email:"secretary@lcbeenachi.test",password:"password"},
  treasurer:{email:"treasurer@lcbeenachi.test",password:"password"},
  member:{email:"local.member@lcbeenachi.test",password:"password"}
};

export default function ReportsBackend({role,onReminders}:{role:Role;onReminders:()=>void}) {
  const [token,setToken]=useState(""); const [clubId,setClubId]=useState(0);
  const [catalog,setCatalog]=useState<Definition[]>([]); const [selected,setSelected]=useState<Definition|null>(null);
  const [report,setReport]=useState<Report|null>(null); const [meta,setMeta]=useState<Meta|null>(null);
  const [state,setState]=useState<"loading"|"ready"|"empty"|"error"|"forbidden">("loading");
  const [message,setMessage]=useState("Loading authorized report catalog...");
  const [filters,setFilters]=useState({q:"",status:"",date_from:"",date_to:""});

  function clearOutput(){setReport(null);setMeta(null);}
  function hasReportValue(value:unknown):boolean {
    if(typeof value==="number")return value!==0;
    if(typeof value==="string")return value!==""&&value!=="0";
    if(Array.isArray(value))return value.some(hasReportValue);
    if(value&&typeof value==="object")return Object.values(value).some(hasReportValue);
    return Boolean(value);
  }
  async function errorText(r:Response,fallback:string){try{const b=await r.json();return Object.values(b.errors??{}).flat()[0] as string||b.message||fallback}catch{return fallback}}
  async function loadCatalog(access:string,club:number){
    clearOutput();setCatalog([]);setState("loading");setMessage("Loading authorized report catalog...");
    try{const r=await fetch(`${API}/clubs/${club}/reports`,{headers:{Accept:"application/json",Authorization:`Bearer ${access}`}});
      if(!r.ok){setState(r.status===403?"forbidden":"error");setMessage(await errorText(r,"Reports are unavailable."));return}
      const b=await r.json();setCatalog(b.data??[]);setState((b.data??[]).length?"ready":"empty");setMessage("Authorized backend report catalog loaded.");
    }catch{setState("error");setMessage("Reports are unavailable. Previous report output was cleared.");}
  }
  useEffect(()=>{let cancelled=false;setToken("");setClubId(0);setCatalog([]);clearOutput();setSelected(null);setState("loading");
    (async()=>{try{const l=await fetch(`${API}/auth/login`,{method:"POST",headers:{Accept:"application/json","Content-Type":"application/json"},body:JSON.stringify(logins[role])});
      if(!l.ok)throw new Error("Report role login failed.");const a=await l.json();const m=await fetch(`${API}/auth/me`,{headers:{Accept:"application/json",Authorization:`Bearer ${a.token}`}});
      if(!m.ok)throw new Error("Report club context is unavailable.");const me=await m.json();const club=me.club_memberships?.[0]?.club?.id;
      if(!a.token||typeof club!=="number")throw new Error("Report club context is unavailable.");if(!cancelled){setToken(a.token);setClubId(club);await loadCatalog(a.token,club)}}
      catch(e){if(!cancelled){setState("error");setMessage(e instanceof Error?e.message:"Reports are unavailable.")}}})();return()=>{cancelled=true}},[role]);
  async function run(def:Definition,page=1,chosen=filters){
    clearOutput();setSelected(def);setState("loading");setMessage(`Loading ${def.title} from verified backend sources...`);
    const q=new URLSearchParams();if(def.allowed_filters.includes("page")){q.set("page",String(page));q.set("per_page","10")}
    for(const [k,v] of Object.entries(chosen))if(v&&def.allowed_filters.includes(k))q.set(k,v);
    try{const r=await fetch(`${API}/clubs/${clubId}/reports/${def.key}?${q}`,{headers:{Accept:"application/json",Authorization:`Bearer ${token}`}});
      if(!r.ok){setState(r.status===403?"forbidden":"error");setMessage(await errorText(r,"Report unavailable."));return}
      const b=await r.json();setReport(b.data);setMeta(b.meta);const empty=(b.data?.rows??[]).length===0&&!Object.values(b.data?.summary??{}).some(hasReportValue);setState(empty?"empty":"ready");setMessage(empty?"This verified report is empty.":"Backend report loaded.");
    }catch{setState("error");setMessage("Report unavailable. Previous rows and totals were cleared.");}
  }
  return <section className="stack">
    <div className="compactPanel"><h2>Reports</h2><p>Backend-calculated · verified provenance · no exports</p></div>
    <div className="notice">{message}</div>
    <div className="buttonRow"><button className="secondaryButton" onClick={onReminders}>Open Reminders</button>{state==="error"&&<button onClick={()=>token&&clubId&&void loadCatalog(token,clubId)}>Retry</button>}</div>
    <div className="tableLike">{catalog.map(d=><article className="staticRow" key={d.key}><strong>{d.title}</strong><span>{d.description}</span><span>{d.aggregate_only?"Aggregate":"Rows"}</span><button className="filterButton" onClick={()=>void run(d)}>Run report</button></article>)}</div>
    {selected&&<div className="compactPanel"><h3>{selected.title} filters</h3><div className="formGrid">
      {selected.allowed_filters.includes("q")&&<label>Search<input value={filters.q} onChange={e=>setFilters({...filters,q:e.target.value})}/></label>}
      {selected.allowed_filters.includes("status")&&<label>Status<input value={filters.status} onChange={e=>setFilters({...filters,status:e.target.value})}/></label>}
      {selected.allowed_filters.includes("date_from")&&<label>From<input type="date" value={filters.date_from} onChange={e=>setFilters({...filters,date_from:e.target.value})}/></label>}
      {selected.allowed_filters.includes("date_to")&&<label>To<input type="date" value={filters.date_to} onChange={e=>setFilters({...filters,date_to:e.target.value})}/></label>}
    </div><div className="buttonRow"><button onClick={()=>void run(selected)}>Apply</button><button className="secondaryButton" onClick={()=>{const f={q:"",status:"",date_from:"",date_to:""};setFilters(f);void run(selected,1,f)}}>Clear</button></div></div>}
    {report&&<section className="stack"><div className="compactPanel"><h2>{report.title}</h2><p>{report.date_basis}</p><p>Generated {report.generated_at} · {report.club_timezone}</p><div className="fieldGrid">{Object.entries(report.summary).map(([k,v])=><div key={k}><strong>{k.replaceAll("_"," ")}</strong><p>{typeof v==="object"?JSON.stringify(v):String(v)}</p></div>)}</div></div><div className="tableLike">{report.rows.map((row,i)=><article className="staticRow" key={String(row.id??i)}>{Object.entries(row).map(([k,v])=><span key={k}><strong>{k.replaceAll("_"," ")}:</strong> {String(v??"")}</span>)}</article>)}</div></section>}
    {meta&&<div className="buttonRow"><button disabled={meta.current_page<=1} onClick={()=>selected&&void run(selected,meta.current_page-1)}>Previous</button><span>Page {meta.current_page} of {meta.last_page} · {meta.total}</span><button disabled={meta.current_page>=meta.last_page} onClick={()=>selected&&void run(selected,meta.current_page+1)}>Next</button></div>}
  </section>;
}
