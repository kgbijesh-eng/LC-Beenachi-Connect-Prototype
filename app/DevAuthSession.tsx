"use client";

import { createContext, type ReactNode, useCallback, useContext, useEffect, useMemo, useState } from "react";

export type DevRole = "president" | "secretary" | "treasurer" | "member";
export type DevIdentity =
  | "bijesh-president"
  | "rajeesh-secretary"
  | "ranjith-treasurer"
  | "sajith-member"
  | "generic-president"
  | "generic-secretary"
  | "generic-treasurer"
  | "generic-member";

export type DevMembership = {
  id: number;
  club_id?: number;
  club: { id: number; name?: string; code?: string };
  role: { slug: string; name?: string };
  member: { id: number; member_code: string; full_name: string } | null;
};

type VerifiedDevSession = {
  identity: DevIdentity;
  role: DevRole;
  token: string;
  user: { id: number; name: string; email: string };
  membership: DevMembership;
};

type DevAuthContextValue = {
  enabled: boolean;
  status: "loading" | "authenticated" | "error" | "disabled";
  session: VerifiedDevSession | null;
  error: string | null;
  switching: boolean;
  restoredFromStorage: boolean;
  switchIdentity: (identity: DevIdentity) => Promise<boolean>;
  authenticatedFetch: (input: string, init?: RequestInit) => Promise<Response>;
};

const API = "http://127.0.0.1:8000/api/v1";
const IDENTITY_KEY = "lcBeenachi.devAuth.identity";
const LEGACY_ROLE_KEY = "lcBeenachi.devAuth.role";
const TOKEN_KEY = "lcBeenachi.devAuth.token";
type TestAccount = {
  email: string;
  password: string;
  role: DevRole;
  roleSlug: string;
  linkedMember?: { memberCode: string; fullName: string };
};
const TEST_ACCOUNTS: Record<DevIdentity, TestAccount> = {
  "bijesh-president": { email: "bijesh.president@lcbeenachi.test", password: "password", role: "president", roleSlug: "president-admin", linkedMember: { memberCode: "4794370", fullName: "Bijesh K G" } },
  "rajeesh-secretary": { email: "rajeesh.secretary@lcbeenachi.test", password: "password", role: "secretary", roleSlug: "secretary", linkedMember: { memberCode: "26766796", fullName: "Rajeesh PR" } },
  "ranjith-treasurer": { email: "ranjith.treasurer@lcbeenachi.test", password: "password", role: "treasurer", roleSlug: "treasurer", linkedMember: { memberCode: "26766860", fullName: "Ranjith MR" } },
  "sajith-member": { email: "sajith.member@lcbeenachi.test", password: "password", role: "member", roleSlug: "member", linkedMember: { memberCode: "4794369", fullName: "Sajith K" } },
  "generic-president": { email: "president@lcbeenachi.test", password: "password", role: "president", roleSlug: "president-admin" },
  "generic-secretary": { email: "secretary@lcbeenachi.test", password: "password", role: "secretary", roleSlug: "secretary" },
  "generic-treasurer": { email: "treasurer@lcbeenachi.test", password: "password", role: "treasurer", roleSlug: "treasurer" },
  "generic-member": { email: "local.member@lcbeenachi.test", password: "password", role: "member", roleSlug: "member" }
};
const DevAuthContext = createContext<DevAuthContextValue | null>(null);

function isIdentity(value: string | null): value is DevIdentity {
  return value !== null && Object.prototype.hasOwnProperty.call(TEST_ACCOUNTS, value);
}

function localDevelopmentAuthAllowed() {
  if (typeof window === "undefined") return false;
  return process.env.NODE_ENV === "development" &&
    process.env.NEXT_PUBLIC_ENABLE_DEV_TEST_AUTH === "true" &&
    (window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1");
}

function clearStoredSession() {
  sessionStorage.removeItem(IDENTITY_KEY);
  sessionStorage.removeItem(LEGACY_ROLE_KEY);
  sessionStorage.removeItem(TOKEN_KEY);
}

function persistSession(session: VerifiedDevSession) {
  sessionStorage.setItem(IDENTITY_KEY, session.identity);
  sessionStorage.setItem(TOKEN_KEY, session.token);
}

async function verifySession(token: string, identity: DevIdentity): Promise<VerifiedDevSession> {
  const response = await fetch(`${API}/auth/me`, { headers: { Accept: "application/json", Authorization: `Bearer ${token}` } });
  if (!response.ok) throw new Error("The stored development session is no longer valid.");
  const payload = await response.json();
  const expected = TEST_ACCOUNTS[identity];
  const user = payload.user as VerifiedDevSession["user"] | undefined;
  const memberships = payload.club_memberships as DevMembership[] | undefined;
  const membership = memberships?.find((item) => item.club?.code === "LCB");
  if (!user || user.email !== expected.email) throw new Error("Development account email verification failed.");
  if (!membership || membership.role?.slug !== expected.roleSlug) throw new Error("Development account role verification failed.");
  if (!Object.prototype.hasOwnProperty.call(membership, "member")) throw new Error("The backend did not return member-link state.");
  if (expected.linkedMember) {
    if (!membership.member || membership.member.member_code !== expected.linkedMember.memberCode || membership.member.full_name !== expected.linkedMember.fullName) {
      throw new Error(`The selected development account is not linked to ${expected.linkedMember.fullName}.`);
    }
  } else if (membership.member !== null) {
    throw new Error("The selected generic development account is unexpectedly linked to a member record.");
  }
  return { identity, role: expected.role, token, user, membership };
}

async function authenticateIdentity(identity: DevIdentity): Promise<VerifiedDevSession> {
  const account = TEST_ACCOUNTS[identity];
  const response = await fetch(`${API}/auth/login`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ email: account.email, password: account.password })
  });
  if (!response.ok) throw new Error(`Development authentication failed for ${identity}.`);
  const payload = await response.json();
  if (typeof payload.token !== "string" || !payload.token) throw new Error("The backend did not return a bearer token.");
  return verifySession(payload.token, identity);
}

export function DevAuthProvider({ children }: { children: ReactNode }) {
  const [enabled, setEnabled] = useState(false);
  const [status, setStatus] = useState<DevAuthContextValue["status"]>("loading");
  const [session, setSession] = useState<VerifiedDevSession | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [switching, setSwitching] = useState(false);
  const [restoredFromStorage, setRestoredFromStorage] = useState(false);

  useEffect(() => {
    let cancelled = false;
    const allowed = localDevelopmentAuthAllowed();
    setEnabled(allowed);
    if (!allowed) { setStatus("disabled"); return () => { cancelled = true; }; }
    void (async () => {
      const storedIdentityValue = sessionStorage.getItem(IDENTITY_KEY);
      const storedIdentity = isIdentity(storedIdentityValue) ? storedIdentityValue : "ranjith-treasurer";
      setRestoredFromStorage(isIdentity(storedIdentityValue));
      const storedToken = sessionStorage.getItem(TOKEN_KEY);
      try {
        let verified: VerifiedDevSession;
        if (storedToken) {
          try { verified = await verifySession(storedToken, storedIdentity); }
          catch { verified = await authenticateIdentity(storedIdentity); }
        } else {
          verified = await authenticateIdentity(storedIdentity);
        }
        if (!cancelled) {
          persistSession(verified); setSession(verified); setStatus("authenticated"); setError(null);
        }
      } catch (cause) {
        clearStoredSession();
        if (!cancelled) {
          setSession(null); setStatus("error");
          setError(cause instanceof Error ? cause.message : "Development authentication failed.");
        }
      }
    })();
    return () => { cancelled = true; };
  }, []);

  const switchIdentity = useCallback(async (identity: DevIdentity) => {
    if (!enabled || switching || session?.identity === identity) return session?.identity === identity;
    setSwitching(true); setError(null);
    const previous = session;
    clearStoredSession();
    setSession(null);
    setStatus("loading");
    try {
      if (previous?.token) {
        await fetch(`${API}/auth/logout`, { method: "POST", headers: { Accept: "application/json", Authorization: `Bearer ${previous.token}` } }).catch(() => undefined);
      }
      const next = await authenticateIdentity(identity);
      persistSession(next); setSession(next); setStatus("authenticated");
      return true;
    } catch (cause) {
      setSession(null); setStatus("error");
      setError(cause instanceof Error ? cause.message : "Development identity switching failed.");
      return false;
    } finally { setSwitching(false); }
  }, [enabled, session, switching]);

  const authenticatedFetch = useCallback((input: string, init: RequestInit = {}) => {
    if (!session) return Promise.reject(new Error("No verified development session is active."));
    const url = input.startsWith("http://") || input.startsWith("https://") ? input : `${API}${input}`;
    return fetch(url, { ...init, headers: { Accept: "application/json", Authorization: `Bearer ${session.token}`, ...init.headers } });
  }, [session]);

  const value = useMemo<DevAuthContextValue>(() => ({ enabled, status, session, error, switching, restoredFromStorage, switchIdentity, authenticatedFetch }),
    [authenticatedFetch, enabled, error, restoredFromStorage, session, status, switchIdentity, switching]);
  return <DevAuthContext.Provider value={value}>{children}</DevAuthContext.Provider>;
}

export function useDevAuth() {
  const context = useContext(DevAuthContext);
  if (!context) throw new Error("useDevAuth must be used inside DevAuthProvider.");
  return context;
}
