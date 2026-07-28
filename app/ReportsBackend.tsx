"use client";

import { useEffect, useState } from "react";
import { useDevAuth } from "./DevAuthSession";

type Role = "president"|"secretary"|"treasurer"|"member";
type Definition = { key:string; title:string; description:string; allowed_filters:string[]; aggregate_only:boolean; sensitivity:string; financial?:boolean };
type Report = { key:string; title:string; description:string; generated_at:string; club_timezone:string; date_basis:string; applied_filters:Record<string,unknown>; summary:Record<string,unknown>; rows:Record<string,unknown>[] };
type Meta = { current_page:number; last_page:number; per_page:number; total:number };
import { getApiBaseUrl } from "./apiBaseUrl";
const API=getApiBaseUrl();
export default function ReportsBackend({role,onReminders}:{role:Role;onReminders:()=>void}) {
  const { session } = useDevAuth();
  const [token,setToken]=useState(""); const [clubId,setClubId]=useState(0);
  const [catalog,setCatalog]=useState<Definition[]>([]); const [selected,setSelected]=useState<Definition|null>(null);
  const [report,setReport]=useState<Report|null>(null); const [meta,setMeta]=useState<Meta|null>(null);
  const [state,setState]=useState<"loading"|"ready"|"empty"|"error"|"forbidden"|"validation">("loading");
  const [message,setMessage]=useState("Loading authorized report catalog...");
  const [filters,setFilters]=useState({q:"",status:"",scope:"",record_type:"",service_category:"",date_from:"",date_to:"",category:"",charge_status:"",payment_status:"",payment_mode:"",member_search:"",charge_search:""});

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
    clearOutput();setSelected(null);setCatalog([]);setState("loading");setMessage("Loading authorized report catalog...");
    try{const r=await fetch(`${API}/clubs/${club}/reports`,{headers:{Accept:"application/json",Authorization:`Bearer ${access}`}});
      if(!r.ok){setState(r.status===403?"forbidden":"error");setMessage(await errorText(r,"Reports are unavailable."));return}
      const b=await r.json();setCatalog(b.data??[]);setState((b.data??[]).length?"ready":"empty");setMessage("Authorized backend report catalog loaded.");
    }catch{setState("error");setMessage("Reports are unavailable. Previous report output was cleared.");}
  }
  useEffect(()=>{let cancelled=false;
    (async()=>{await Promise.resolve();if(cancelled)return;setToken("");setClubId(0);setCatalog([]);clearOutput();setSelected(null);setState("loading");try{if(!session)throw new Error("No verified development session is available.");const club=session.membership.club.id;
      if(!cancelled){setToken(session.token);setClubId(club);await loadCatalog(session.token,club)}}
      catch(e){if(!cancelled){setState("error");setMessage(e instanceof Error?e.message:"Reports are unavailable.")}}})();return()=>{cancelled=true}},[role,session]);
  async function run(def:Definition,page=1,chosen=filters){
    clearOutput();setSelected(def);setState("loading");setMessage(`Loading ${def.title} from verified backend sources...`);
    const q=new URLSearchParams();if(def.allowed_filters.includes("page")){q.set("page",String(page));q.set("per_page","10")}
    for(const [k,v] of Object.entries(chosen))if(v&&def.allowed_filters.includes(k))q.set(k,v);
    try{const r=await fetch(`${API}/clubs/${clubId}/reports/${def.key}?${q}`,{headers:{Accept:"application/json",Authorization:`Bearer ${token}`}});
      if(!r.ok){setState(r.status===403?"forbidden":r.status===422?"validation":"error");setMessage(await errorText(r,"Report unavailable."));return}
      const b=await r.json();setReport(b.data);setMeta(b.meta);const empty=(b.data?.rows??[]).length===0&&!Object.values(b.data?.summary??{}).some(hasReportValue);setState(empty?"empty":"ready");setMessage(empty?"This verified report is empty.":"Backend report loaded.");
    }catch{setState("error");setMessage("Report unavailable. Previous rows and totals were cleared.");}
  }
  return <section className="stack">
    <div className="compactPanel"><h2>Reports</h2><p>Backend-calculated · verified provenance · integer paise · INR · no exports</p></div>
    <div className="notice">{message}</div>
    <div className="buttonRow"><button className="secondaryButton" onClick={onReminders}>Open Reminders</button>{state==="error"&&<button onClick={()=>token&&clubId&&void loadCatalog(token,clubId)}>Retry</button>}</div>
    <div className="tableLike">{catalog.map(d=><article className="staticRow" key={d.key}><strong>{d.title}</strong><span>{d.description}</span><span>{d.financial?"Financial · INR":d.aggregate_only?"Aggregate":"Rows"}</span><button className="filterButton" onClick={()=>void run(d)}>Run report</button></article>)}</div>
    {selected&&<div className="compactPanel"><h3>{selected.title} filters</h3><div className="formGrid">
      {selected.allowed_filters.includes("q")&&<label>Search<input value={filters.q} onChange={e=>setFilters({...filters,q:e.target.value})}/></label>}
      {selected.allowed_filters.includes("status")&&<label>Status<input value={filters.status} onChange={e=>setFilters({...filters,status:e.target.value})}/></label>}
      {selected.allowed_filters.includes("scope")&&<label>Period<select value={filters.scope} onChange={e=>setFilters({...filters,scope:e.target.value})}><option value="">All records</option><option value="upcoming">Upcoming or ongoing</option><option value="past">Past</option></select></label>}
      {selected.allowed_filters.includes("record_type")&&<label>Record type<select value={filters.record_type} onChange={e=>setFilters({...filters,record_type:e.target.value})}><option value="">All types</option><option value="project">Projects</option><option value="activity">Activities</option></select></label>}
      {selected.allowed_filters.includes("service_category")&&<label>Service category<input value={filters.service_category} onChange={e=>setFilters({...filters,service_category:e.target.value})}/></label>}
      {selected.allowed_filters.includes("category")&&<label>Category<input value={filters.category} onChange={e=>setFilters({...filters,category:e.target.value})}/></label>}
      {selected.allowed_filters.includes("charge_status")&&<label>Charge status<input value={filters.charge_status} onChange={e=>setFilters({...filters,charge_status:e.target.value})}/></label>}
      {selected.allowed_filters.includes("payment_status")&&<label>Payment status<input value={filters.payment_status} onChange={e=>setFilters({...filters,payment_status:e.target.value})}/></label>}
      {selected.allowed_filters.includes("payment_mode")&&<label>Payment mode<input value={filters.payment_mode} onChange={e=>setFilters({...filters,payment_mode:e.target.value})}/></label>}
      {selected.allowed_filters.includes("member_search")&&<label>Member search<input value={filters.member_search} onChange={e=>setFilters({...filters,member_search:e.target.value})}/></label>}
      {selected.allowed_filters.includes("charge_search")&&<label>Charge search<input value={filters.charge_search} onChange={e=>setFilters({...filters,charge_search:e.target.value})}/></label>}
      {selected.allowed_filters.includes("date_from")&&<label>From<input type="date" value={filters.date_from} onChange={e=>setFilters({...filters,date_from:e.target.value})}/></label>}
      {selected.allowed_filters.includes("date_to")&&<label>To<input type="date" value={filters.date_to} onChange={e=>setFilters({...filters,date_to:e.target.value})}/></label>}
    </div><div className="buttonRow"><button onClick={()=>void run(selected)}>Apply</button><button className="secondaryButton" onClick={()=>{const f={q:"",status:"",scope:"",record_type:"",service_category:"",date_from:"",date_to:"",category:"",charge_status:"",payment_status:"",payment_mode:"",member_search:"",charge_search:""};setFilters(f);void run(selected,1,f)}}>Clear</button></div></div>}
    {report&&<section className="stack"><div className="compactPanel"><h2>{report.title}</h2><p>{report.date_basis}</p><p>Generated {report.generated_at} · {report.club_timezone}</p><div className="fieldGrid">{Object.entries(report.summary).map(([k,v])=><div key={k}><strong>{k.replaceAll("_"," ")}</strong><p>{typeof v==="object"?JSON.stringify(v):String(v)}</p></div>)}</div></div><div className="tableLike">{report.rows.map((row,i)=><article className="staticRow" key={String(row.id??i)}>{Object.entries(row).map(([k,v])=><span key={k}><strong>{k.replaceAll("_"," ")}:</strong> {String(v??"")}</span>)}</article>)}</div></section>}
    {meta&&<div className="buttonRow"><button disabled={meta.current_page<=1} onClick={()=>selected&&void run(selected,meta.current_page-1)}>Previous</button><span>Page {meta.current_page} of {meta.last_page} · {meta.total}</span><button disabled={meta.current_page>=meta.last_page} onClick={()=>selected&&void run(selected,meta.current_page+1)}>Next</button></div>}
  </section>;
}
