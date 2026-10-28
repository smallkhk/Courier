import { redirect } from "next/navigation";
import { currentUser, type CurrentUser } from "./auth.ts";

// For server-rendered pages: the signed-in user, or a redirect to sign in.
export async function pageUser(): Promise<CurrentUser> {
  const user = await currentUser();
  if (!user) redirect("/");
  return user;
}
