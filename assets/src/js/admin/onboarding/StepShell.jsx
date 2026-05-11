import { Button } from "@/js/shared/ui/button.jsx";

/**
 * Layout chrome for an onboarding step. Steps render their own body inside
 * `children`; the shell owns navigation + the visual step indicator so
 * every step feels consistent.
 *
 * Props:
 *   step       — current 1-based step number
 *   total      — total number of steps
 *   title      — h1 of the step
 *   subtitle   — small lead under the title (optional)
 *   children   — step body
 *   onBack     — fn() | null (null hides the Back button — typically step 1)
 *   onNext     — fn() | null (null disables Next; Done step replaces with a custom CTA)
 *   onSkip     — fn() (sets dismissed and exits; always present except on the Done step)
 *   nextLabel  — defaults to "Next"; the Done step uses "Finish"
 *   nextDisabled — boolean; useful when a prereq fails
 *   nextBusy   — boolean; mutates the button into a spinner during async work
 *   error      — optional error string rendered above the footer
 */
export function StepShell({
	step,
	total,
	title,
	subtitle,
	children,
	onBack,
	onNext,
	onSkip,
	nextLabel = "Next",
	nextDisabled = false,
	nextBusy = false,
	error,
}) {
	return (
		<div className="zc-min-h-screen zc-bg-zinc-50">
			<div className="zc-mx-auto zc-max-w-3xl zc-px-6 zc-py-10">
				<StepIndicator step={step} total={total} />

				<div className="zc-mt-6 zc-rounded-xl zc-border zc-border-zinc-200 zc-bg-white zc-shadow-sm">
					<header className="zc-border-b zc-border-zinc-100 zc-px-8 zc-py-6">
						<h1 className="zc-text-2xl zc-font-semibold zc-text-zinc-900">{title}</h1>
						{subtitle ? (
							<p className="zc-mt-1 zc-text-sm zc-text-zinc-500">{subtitle}</p>
						) : null}
					</header>

					<div className="zc-px-8 zc-py-6">
						{children}
					</div>

					{error ? (
						<div className="zc-mx-8 zc-mb-4 zc-rounded-md zc-border zc-border-rose-200 zc-bg-rose-50 zc-px-3 zc-py-2 zc-text-sm zc-text-rose-800">
							{error}
						</div>
					) : null}

					<footer className="zc-flex zc-items-center zc-justify-between zc-border-t zc-border-zinc-100 zc-bg-zinc-50/50 zc-px-8 zc-py-4">
						<div>
							{onBack ? (
								<Button variant="ghost" onClick={onBack} disabled={nextBusy}>
									← Back
								</Button>
							) : <span /> /* keep flex layout */}
						</div>

						<div className="zc-flex zc-items-center zc-gap-3">
							{onSkip ? (
								<button
									type="button"
									onClick={onSkip}
									disabled={nextBusy}
									className="zc-text-xs zc-text-zinc-500 hover:zc-text-zinc-800 disabled:zc-cursor-not-allowed disabled:zc-opacity-50"
								>
									Skip for now
								</button>
							) : null}
							{onNext ? (
								<Button
									onClick={onNext}
									disabled={nextDisabled || nextBusy}
									loading={nextBusy}
								>
									{nextLabel}
								</Button>
							) : null}
						</div>
					</footer>
				</div>
			</div>
		</div>
	);
}

/**
 * Pill-style step indicator. Filled past the current step, hollow ahead.
 * Click target opens that step — admins can jump back to revisit a step
 * they've already passed. Forward jumps are disabled (no skipping ahead).
 */
function StepIndicator({ step, total }) {
	return (
		<ol className="zc-flex zc-items-center zc-gap-2" aria-label="Setup progress">
			{Array.from({ length: total }, (_, i) => {
				const n = i + 1;
				const isPast    = n < step;
				const isCurrent = n === step;
				return (
					<li key={n} className="zc-flex zc-items-center zc-gap-2">
						<span
							className={[
								"zc-flex zc-h-7 zc-w-7 zc-items-center zc-justify-center zc-rounded-full zc-text-xs zc-font-semibold zc-transition-colors",
								isPast    ? "zc-bg-emerald-500 zc-text-white" :
								isCurrent ? "zc-bg-zinc-900 zc-text-white"    :
								             "zc-bg-zinc-200 zc-text-zinc-500",
							].join(" ")}
							aria-current={isCurrent ? "step" : undefined}
						>
							{isPast ? "✓" : n}
						</span>
						{n < total ? (
							<span
								className={[
									"zc-h-px zc-w-8 zc-transition-colors",
									isPast ? "zc-bg-emerald-500" : "zc-bg-zinc-200",
								].join(" ")}
							/>
						) : null}
					</li>
				);
			})}
		</ol>
	);
}
