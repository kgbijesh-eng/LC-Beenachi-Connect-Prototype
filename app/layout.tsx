import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "LC Beenachi Connect UI Prototype",
  description: "Mobile-first clickable UI prototype for LC Beenachi Connect V1"
};

export default function RootLayout({
  children
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
