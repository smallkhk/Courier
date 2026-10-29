import { NextResponse } from "next/server";
import { z } from "zod";
import { currentUser } from "./auth.ts";
import { ElevenLabsError } from "./elevenlabs.ts";
import { HttpError } from "./errors.ts";

export function json(data: unknown, status = 200): NextResponse {
  return NextResponse.json(data, { status });
}

// Wraps a route handler so thrown HttpErrors and validation errors become
// JSON error responses instead of 500s.
export function route<Args extends unknown[]>(
  handler: (...args: Args) => Promise<Response>,
): (...args: Args) => Promise<Response> {
  return async (...args: Args) => {
    try {
      return await handler(...args);
    } catch (err) {
      if (err instanceof HttpError) return json({ error: err.message }, err.status);
      if (err instanceof z.ZodError) {
        return json({ error: err.issues[0]?.message ?? "Invalid request" }, 400);
      }
      if (err instanceof ElevenLabsError) {
        console.error(err.message);
        // Admins see ElevenLabs' own reason so they can fix the setup;
        // customers get a plain message.
        const user = await currentUser().catch(() => null);
        const detail = user?.isAdmin ? ` (Admin only: ${err.message})` : "";
        return json({ error: `The voice service isn't available right now. Please try again shortly.${detail}` }, 502);
      }
      console.error(err);
      return json({ error: "Something went wrong. Please try again." }, 500);
    }
  };
}

export async function readJson<T>(request: Request, schema: z.ZodType<T>): Promise<T> {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    throw new HttpError(400, "Request body must be JSON");
  }
  return schema.parse(body);
}
