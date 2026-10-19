import type { InputHTMLAttributes, ReactNode } from "react";

export default function FormField({
  label,
  error,
  hint,
  id,
  ...props
}: InputHTMLAttributes<HTMLInputElement> & {
  label: string;
  error?: string;
  hint?: ReactNode;
}) {
  const message = error ?? hint;
  const descriptionId = message ? `${id}-description` : undefined;

  return (
    <div>
      <label htmlFor={id} className="mb-1.5 block text-sm font-semibold text-ink">
        {label}
      </label>
      <input
        {...props}
        id={id}
        aria-invalid={Boolean(error)}
        aria-describedby={descriptionId}
        className={`w-full rounded border bg-white px-3 py-2.5 text-sm text-ink outline-none transition focus-visible:ring-2 focus-visible:ring-navy/20 ${
          error
            ? "border-red-600 focus:border-red-600"
            : "border-slate-300 focus:border-navy"
        }`}
      />
      {message && (
        <p
          id={descriptionId}
          className={`mt-1.5 text-xs leading-5 ${error ? "text-red-700" : "text-muted"}`}
        >
          {message}
        </p>
      )}
    </div>
  );
}
