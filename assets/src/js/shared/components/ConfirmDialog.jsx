import { createContext, useCallback, useContext, useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { Button } from "@/js/shared/ui/button.jsx";

/**
 * Themed confirm dialog. Replaces window.confirm() in admin panels so we
 * get a consistent look + can mark destructive actions with the danger
 * tone without hand-rolling a modal in every caller.
 *
 * Two pieces here:
 *   1. ConfirmProvider — mount once near the React root
 *   2. useConfirm()   — returns an async function callers await
 *
 * Usage:
 *   const confirm = useConfirm();
 *   const ok = await confirm({
 *     title: "Pause voucher?",
 *     message: "Customers won't see it until you resume.",
 *     confirmLabel: "Pause",
 *     tone: "danger",       // 'default' | 'danger'  (defaults to 'default')
 *   });
 *   if (!ok) return;
 *
 * The promise resolves true on confirm, false on cancel/Escape/backdrop.
 *
 * `loading` mode: pass a fn instead of awaiting — `await confirm({..., onConfirm: async () => mutate()})`
 * keeps the dialog open with a spinner on the confirm button until the
 * promise settles, then auto-closes. Errors surface as a destructive notice
 * banner inside the dialog (admin can retry without losing intent).
 */

const ConfirmContext = createContext(null);

export function ConfirmProvider({ children }) {
	const [opts, setOpts]         = useState(null);   // null = closed
	const [loading, setLoading]   = useState(false);
	const [error, setError]       = useState(null);
	const resolverRef             = useRef(null);

	const confirm = useCallback((options) => {
		setError(null);
		setLoading(false);
		setOpts(options);
		return new Promise((resolve) => {
			resolverRef.current = resolve;
		});
	}, []);

	const close = useCallback((value) => {
		const resolve = resolverRef.current;
		resolverRef.current = null;
		setOpts(null);
		setLoading(false);
		setError(null);
		if (resolve) resolve(value);
	}, []);

	const onConfirm = useCallback(async () => {
		if (!opts) return;
		// If caller wired `onConfirm`, run it inside the dialog so the spinner
		// + error notice work. Otherwise just resolve(true) and close.
		if (typeof opts.onConfirm === "function") {
			setError(null);
			setLoading(true);
			try {
				await opts.onConfirm();
				close(true);
			} catch (err) {
				setLoading(false);
				setError(err?.message || "Could not complete the action.");
			}
		} else {
			close(true);
		}
	}, [opts, close]);

	const onCancel = useCallback(() => {
		if (loading) return; // can't cancel an in-flight action
		close(false);
	}, [loading, close]);

	return (
		<ConfirmContext.Provider value={confirm}>
			{children}
			{opts ? (
				<ConfirmDialog
					opts={opts}
					loading={loading}
					error={error}
					onConfirm={onConfirm}
					onCancel={onCancel}
				/>
			) : null}
		</ConfirmContext.Provider>
	);
}

export function useConfirm() {
	const ctx = useContext(ConfirmContext);
	if (!ctx) {
		// Easier to spot than a runtime null deref. Drop a helpful message.
		throw new Error("useConfirm must be used inside <ConfirmProvider>.");
	}
	return ctx;
}

function ConfirmDialog({ opts, loading, error, onConfirm, onCancel }) {
	const { title, message, confirmLabel = "Confirm", cancelLabel = "Cancel", tone = "default" } = opts;
	const confirmRef = useRef(null);

	// Autofocus the confirm button so Enter commits, Escape cancels.
	useEffect(() => {
		const t = setTimeout(() => confirmRef.current?.focus(), 0);
		return () => clearTimeout(t);
	}, []);

	// Esc + Enter wiring. Esc cancels (unless loading). Enter confirms.
	useEffect(() => {
		const onKey = (e) => {
			if (e.key === "Escape") { e.preventDefault(); onCancel(); }
			if (e.key === "Enter")  { e.preventDefault(); onConfirm(); }
		};
		window.addEventListener("keydown", onKey);
		return () => window.removeEventListener("keydown", onKey);
	}, [onCancel, onConfirm]);

	return createPortal(
		<div className="zc-fixed zc-inset-0 zc-z-[100002] zc-flex zc-items-center zc-justify-center zc-p-4">
			<div
				onClick={onCancel}
				className="zc-absolute zc-inset-0 zc-bg-zinc-900/40 zc-backdrop-blur-sm"
				aria-hidden
			/>
			<div
				role="dialog"
				aria-modal="true"
				aria-labelledby="zc-confirm-title"
				className="zc-relative zc-w-full zc-max-w-md zc-overflow-hidden zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-white zc-shadow-2xl"
			>
				<div className="zc-flex zc-items-start zc-gap-3 zc-p-5">
					<ToneIcon tone={tone} />
					<div className="zc-min-w-0 zc-flex-1">
						<h3 id="zc-confirm-title" className="zc-text-base zc-font-semibold zc-text-zinc-900">
							{title}
						</h3>
						{message ? (
							<p className="zc-mt-1 zc-text-sm zc-text-zinc-600">{message}</p>
						) : null}
						{error ? (
							<p className="zc-mt-3 zc-rounded-md zc-border zc-border-rose-200 zc-bg-rose-50 zc-px-3 zc-py-2 zc-text-sm zc-text-rose-800">
								{error}
							</p>
						) : null}
					</div>
				</div>
				<div className="zc-flex zc-items-center zc-justify-end zc-gap-2 zc-border-t zc-border-zinc-200 zc-bg-zinc-50 zc-px-5 zc-py-3">
					<Button
						type="button"
						variant="ghost"
						onClick={onCancel}
						disabled={loading}
					>
						{cancelLabel}
					</Button>
					<Button
						ref={confirmRef}
						type="button"
						variant={tone === "danger" ? "danger" : "primary"}
						onClick={onConfirm}
						loading={loading}
					>
						{confirmLabel}
					</Button>
				</div>
			</div>
		</div>,
		document.body,
	);
}

function ToneIcon({ tone }) {
	if (tone === "danger") {
		return (
			<div className="zc-flex zc-size-9 zc-shrink-0 zc-items-center zc-justify-center zc-rounded-full zc-bg-rose-100 zc-text-rose-600">
				<svg viewBox="0 0 24 24" className="zc-size-5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
					<path strokeLinecap="round" strokeLinejoin="round" d="M12 9v4m0 4h.01M5.07 19h13.86a2 2 0 0 0 1.74-3l-6.93-12a2 2 0 0 0-3.48 0L3.33 16a2 2 0 0 0 1.74 3z" />
				</svg>
			</div>
		);
	}
	return (
		<div className="zc-flex zc-size-9 zc-shrink-0 zc-items-center zc-justify-center zc-rounded-full zc-bg-zinc-100 zc-text-zinc-700">
			<svg viewBox="0 0 24 24" className="zc-size-5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
				<circle cx="12" cy="12" r="9" />
				<path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4M12 16h.01" />
			</svg>
		</div>
	);
}
