import { useState } from "react";
import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { StepShell } from "../StepShell.jsx";

/**
 * Step 5 — Notifications explainer + the "Send test email" button. This is
 * the only step with a real side effect: clicking the button hits
 * POST /admin/onboarding/test-email and the admin gets a sample voucher
 * email in their inbox.
 *
 * Rate-limited server-side to 1 send/minute/user; we surface a friendly
 * error on 429.
 */
export function NotificationsStep({ step, total, onBack, onNext, onSkip }) {
	const [result, setResult] = useState(null); // { kind: "ok"|"err", message?: string, sentTo?: string }

	const send = useApiMutation("post", "/admin/onboarding/test-email", {
		onSuccess: (data) => setResult({ kind: "ok", sentTo: data?.sent_to }),
		onError:   (err)  => setResult({ kind: "err", message: err?.message ?? "Could not send." }),
	});

	return (
		<StepShell
			step={step}
			total={total}
			title="Notifications"
			subtitle="Customers opt in at registration. When you publish a voucher, opted-in members get an email."
			onBack={onBack}
			onNext={onNext}
			onSkip={onSkip}
		>
			<div className="zc-space-y-6">
				<section>
					<h2 className="zc-text-sm zc-font-semibold zc-uppercase zc-tracking-wider zc-text-zinc-500">
						How it works
					</h2>
					<ul className="zc-mt-3 zc-space-y-2 zc-text-sm zc-text-zinc-700">
						<li>• Registration form has two checkboxes (Vouchers / Points) — both checked by default</li>
						<li>• Customers can change preferences anytime from <strong>My Account → Notifications</strong></li>
						<li>• Tier-restricted vouchers only email customers in the allowed tiers</li>
						<li>• Emails are batched and retried on failure (no lost messages, no double-sends)</li>
					</ul>
				</section>

				<section className="zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-white zc-p-5">
					<h2 className="zc-text-base zc-font-semibold zc-text-zinc-900">Send yourself a test email</h2>
					<p className="zc-mt-1 zc-text-sm zc-text-zinc-600">
						Recommended before you launch — confirms SPF/DKIM/SMTP works and the template renders in your
						customers' clients (Gmail, Apple Mail, Outlook). The test arrives at your admin email.
					</p>

					<div className="zc-mt-4 zc-flex zc-items-center zc-gap-3">
						<Button
							onClick={() => { setResult(null); send.mutate({}); }}
							loading={send.isPending}
							disabled={send.isPending}
						>
							Send test email
						</Button>

						{result?.kind === "ok" ? (
							<span className="zc-text-sm zc-text-emerald-700">
								✓ Sent to <strong>{result.sentTo}</strong>. Check your inbox.
							</span>
						) : null}
						{result?.kind === "err" ? (
							<span className="zc-text-sm zc-text-rose-700">
								{result.message}
							</span>
						) : null}
					</div>

					<p className="zc-mt-3 zc-text-xs zc-text-zinc-500">
						Doesn't arrive? Check WP's mail config (e.g. WP Mail SMTP) and your spam folder. The test
						bypasses customer notification preferences — it always sends to you.
					</p>
				</section>
			</div>
		</StepShell>
	);
}
