"use client";

import { useRouter } from "next/navigation";
import AccountActivationScreen from "../AccountActivationScreen";

export default function ActivatePage() {
  const router = useRouter();

  return <AccountActivationScreen onReturnToLogin={() => router.replace("/")} />;
}
