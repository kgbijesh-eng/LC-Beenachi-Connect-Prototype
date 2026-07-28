"use client";

import { createContext, type ReactNode, useCallback, useContext, useMemo, useState } from "react";

export type DevRole = "president" | "secretary" | "treasurer" | "member";
export type DevIdentity = string;

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
  status: "unauthenticated" | "authenticating" | "authenticated";
  session: VerifiedDevSession | null;
  error: string | null;
  login: (email: string, password: string) => Promise<boolean>;
  logout: () => Promise<void>;
  authenticatedFetch: (input: string, init?: RequestInit) => Promise<Response>;
};

const API = "http://127.0.0.1:8000/api/v1";
const GENERIC_LOGIN_ERROR = "Unable to sign in with the provided credentials.";
const DevAuthContext = createContext<DevAuthContextValue | null>(null);

function roleFromMembership(membership: DevMembership): DevRole {
  const slug = membership.role?.slug;
  if (slug === "president" || slug === "president-admin") return "president";
  if (slug === "secretary") return "secretary";
  if (slug === "treasurer") return "treasurer";
  return "member";
}

async function verifySession(token: string): Promise<VerifiedDevSession> {
  const response = await fetch(`${API}/auth/me`, {
    headers: { Accept: "application/json", Authorization: `Bearer ${token}` }
  });
  if (!response.ok) throw new Error("Session verification failed.");

  const payload = await response.json();
  const user = payload.user as VerifiedDevSession["user"] | undefined;
  const memberships = payload.club_memberships as DevMembership[] | undefined;
  const membership = memberships?.find((item) => item.club?.code === "LCB") ?? memberships?.[0];
  if (!user || typeof user.email !== "string" || !membership) {
    throw new Error("Session verification failed.");
  }

  return {
    identity: user.email,
    role: roleFromMembership(membership),
    token,
    user,
    membership
  };
}

export function DevAuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<DevAuthContextValue["status"]>("unauthenticated");
  const [session, setSession] = useState<VerifiedDevSession | null>(null);
  const [error, setError] = useState<string | null>(null);

  const login = useCallback(async (email: string, password: string) => {
    if (status === "authenticating") return false;
    setStatus("authenticating");
    setError(null);
    setSession(null);

    try {
      const response = await fetch(`${API}/auth/login`, {
        method: "POST",
        headers: { Accept: "application/json", "Content-Type": "application/json" },
        body: JSON.stringify({ email, password })
      });
      if (!response.ok) throw new Error("Authentication failed.");

      const payload = await response.json();
      if (typeof payload.token !== "string" || !payload.token) {
        throw new Error("Authentication failed.");
      }

      const verified = await verifySession(payload.token);
      setSession(verified);
      setStatus("authenticated");
      return true;
    } catch {
      setSession(null);
      setStatus("unauthenticated");
      setError(GENERIC_LOGIN_ERROR);
      return false;
    }
  }, [status]);

  const logout = useCallback(async () => {
    const token = session?.token;
    setSession(null);
    setError(null);
    setStatus("unauthenticated");
    if (!token) return;

    try {
      await fetch(`${API}/auth/logout`, {
        method: "POST",
        headers: { Accept: "application/json", Authorization: `Bearer ${token}` }
      });
    } catch {
      // Local authentication state is intentionally cleared even when logout fails.
    }
  }, [session]);

  const authenticatedFetch = useCallback((input: string, init: RequestInit = {}) => {
    if (!session) return Promise.reject(new Error("No verified session is active."));
    const url = input.startsWith("http://") || input.startsWith("https://") ? input : `${API}${input}`;
    return fetch(url, {
      ...init,
      headers: { Accept: "application/json", Authorization: `Bearer ${session.token}`, ...init.headers }
    });
  }, [session]);

  const value = useMemo<DevAuthContextValue>(() => ({
    enabled: true,
    status,
    session,
    error,
    login,
    logout,
    authenticatedFetch
  }), [authenticatedFetch, error, login, logout, session, status]);

  return <DevAuthContext.Provider value={value}>{children}</DevAuthContext.Provider>;
}

export function useDevAuth() {
  const context = useContext(DevAuthContext);
  if (!context) throw new Error("useDevAuth must be used inside DevAuthProvider.");
  return context;
}
