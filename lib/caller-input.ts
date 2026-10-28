import { z } from "zod";
import { isTimezone } from "./call-prompt.ts";
import { isLanguage } from "./languages.ts";

export const CallerInput = z
  .object({
    name: z.string().trim().min(1, "Enter their name").max(80),
    relationship: z.string().trim().max(80),
    about: z.string().trim().max(4000),
    how_we_talk: z.string().trim().max(2000),
    location: z.string().trim().max(120),
    timezone: z.string().refine(isTimezone, "Pick a valid time zone"),
    language: z.string().refine(isLanguage, "Pick a language from the list"),
    voice_id: z.string().min(1, "Pick a voice"),
    opening: z.enum(["wait", "greet", "custom"]),
    custom_opening: z.string().trim().max(300),
  })
  .refine((c) => c.opening !== "custom" || c.custom_opening.length > 0, {
    message: "Write the opening line, or pick another opening",
  });
