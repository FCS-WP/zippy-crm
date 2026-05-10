import { useEffect, useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { Input } from "@/js/shared/ui/input.jsx";
import { Drawer } from "../Drawer.jsx";

/**
 * Allowed-customers / allowed-emails picker.
 *
 * Storage shape (in voucher.email_restrictions JSON column):
 *   - new shape: array of { email, first_name?, last_name? }
 *   - legacy shape: array of strings (raw emails)
 * Both are accepted on read. We always emit the new object shape on edit.
 *
 * The component shows chips of selected entries plus an "Add customers"
 * button that opens a Drawer with two flows:
 *   1. Search registered users (GET /admin/catalog/customers) — pick by row
 *   2. Add a guest email (text input + Add) — for anyone not in wp_users
 *
 * Empty list = "no email restriction" (anyone can use the voucher).
 */
export function EmailRestrictionsField({ value, onChange }) {
	const entries = normalize(value);
	const [open, setOpen] = useState(false);

	const remove = (email) => onChange(entries.filter((e) => e.email !== email));

	return (
		<div className="zc-space-y-2">
			<div className="zc-flex zc-flex-wrap zc-items-center zc-gap-1.5 zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-p-2">
				{entries.length === 0 ? (
					<span className="zc-px-1 zc-text-xs zc-text-zinc-400">
						Empty — anyone can use this voucher.
					</span>
				) : (
					entries.map((entry) => (
						<EmailChip key={entry.email} entry={entry} onRemove={() => remove(entry.email)} />
					))
				)}
				<button
					type="button"
					onClick={() => setOpen(true)}
					className="zc-ml-auto zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-px-2 zc-py-1 zc-text-xs zc-font-medium zc-text-zinc-700 hover:zc-bg-zinc-50"
				>
					+ Add customers
				</button>
			</div>
			<p className="zc-text-xs zc-text-zinc-500">
				Pick registered users or add guest emails. WC compares emails case-insensitively at checkout.
			</p>

			<Drawer
				open={open}
				onClose={() => setOpen(false)}
				title="Allowed customers"
				width="zc-max-w-md"
			>
				<PickerBody
					entries={entries}
					onChange={onChange}
					onClose={() => setOpen(false)}
				/>
			</Drawer>
		</div>
	);
}

function EmailChip({ entry, onRemove }) {
	const display = displayLabel(entry);
	const sub     = display !== entry.email ? entry.email : null;
	return (
		<span
			className="zc-inline-flex zc-items-center zc-gap-1.5 zc-rounded-full zc-bg-zinc-100 zc-py-0.5 zc-pl-2.5 zc-pr-1 zc-text-xs zc-font-medium zc-text-zinc-800"
			title={sub ? `${display} <${entry.email}>` : entry.email}
		>
			<span className="zc-truncate zc-max-w-[16rem]">
				{display}
				{sub ? <span className="zc-ml-1 zc-text-zinc-500">&lt;{sub}&gt;</span> : null}
			</span>
			<button
				type="button"
				onClick={onRemove}
				aria-label={`Remove ${entry.email}`}
				className="zc-rounded-full zc-p-0.5 zc-text-zinc-500 hover:zc-bg-zinc-200 hover:zc-text-zinc-900"
			>
				<svg viewBox="0 0 24 24" className="zc-size-3" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden>
					<path strokeLinecap="round" d="M6 6l12 12M18 6L6 18" />
				</svg>
			</button>
		</span>
	);
}

function PickerBody({ entries, onChange, onClose }) {
	const [tab, setTab] = useState("users");

	return (
		<div className="zc-space-y-3">
			<div className="zc-inline-flex zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-zinc-50 zc-p-1">
				<TabBtn active={tab === "users"}  onClick={() => setTab("users")}>Registered users</TabBtn>
				<TabBtn active={tab === "guest"}  onClick={() => setTab("guest")}>Guest email</TabBtn>
			</div>

			{tab === "users"
				? <UsersPicker entries={entries} onChange={onChange} />
				: <GuestEmailForm entries={entries} onChange={onChange} />}

			<div className="zc-flex zc-items-center zc-justify-between zc-border-t zc-border-zinc-200 zc-pt-3">
				<span className="zc-text-xs zc-text-zinc-500">
					{entries.length} {entries.length === 1 ? "customer" : "customers"} allowed
				</span>
				<Button size="sm" onClick={onClose}>Done</Button>
			</div>
		</div>
	);
}

function TabBtn({ active, onClick, children }) {
	return (
		<button
			type="button"
			onClick={onClick}
			className={[
				"zc-rounded-md zc-px-3 zc-py-1.5 zc-text-xs zc-font-medium zc-transition-colors",
				active
					? "zc-bg-white zc-text-zinc-900 zc-shadow-sm"
					: "zc-text-zinc-600 hover:zc-text-zinc-900",
			].join(" ")}
		>
			{children}
		</button>
	);
}

function UsersPicker({ entries, onChange }) {
	const [search, setSearch] = useState("");
	const debounced = useDebounced(search, 250);

	const list = useApiQuery("/admin/catalog/customers", {
		params: { search: debounced, per_page: 30 },
		enabled: debounced.length > 0,
	});
	const items = list.data?.items ?? [];

	const selectedEmails = new Set(entries.map((e) => e.email));

	const toggle = (user) => {
		const email = (user.email || "").toLowerCase();
		if (!email) return;
		if (selectedEmails.has(email)) {
			onChange(entries.filter((e) => e.email !== email));
		} else {
			onChange([
				...entries,
				{
					email,
					first_name: user.first_name || "",
					last_name:  user.last_name  || "",
				},
			]);
		}
	};

	return (
		<div className="zc-space-y-2">
			<Input
				type="text"
				autoFocus
				placeholder="Search users by name, login, or email…"
				value={search}
				onChange={(e) => setSearch(e.target.value)}
			/>

			{debounced === "" ? (
				<p className="zc-rounded-md zc-border zc-border-dashed zc-border-zinc-200 zc-p-4 zc-text-center zc-text-xs zc-text-zinc-500">
					Start typing to search.
				</p>
			) : list.isLoading ? (
				<p className="zc-text-sm zc-text-zinc-500">Searching…</p>
			) : list.error ? (
				<p className="zc-text-sm zc-text-rose-700">{list.error.message || "Search failed."}</p>
			) : items.length === 0 ? (
				<p className="zc-text-sm zc-text-zinc-500">No matches.</p>
			) : (
				<ul className="zc-divide-y zc-divide-zinc-100 zc-overflow-hidden zc-rounded-md zc-border zc-border-zinc-200">
					{items.map((user) => {
						const email   = (user.email || "").toLowerCase();
						const checked = selectedEmails.has(email);
						const fullName = [ user.first_name, user.last_name ].filter(Boolean).join(" ");
						const heading  = fullName || user.display_name || user.login || "(no name)";
						return (
							<li key={user.id}>
								<label className="zc-flex zc-cursor-pointer zc-items-center zc-gap-3 zc-bg-white zc-px-3 zc-py-2 zc-text-sm hover:zc-bg-zinc-50">
									<input
										type="checkbox"
										checked={checked}
										onChange={() => toggle(user)}
										className="zc-size-4"
									/>
									<div className="zc-min-w-0 zc-flex-1">
										<div className="zc-truncate zc-font-medium zc-text-zinc-900">{heading}</div>
										<div className="zc-truncate zc-text-xs zc-text-zinc-500">
											{user.email}
											{user.login && user.login !== heading ? (
												<span className="zc-ml-1.5 zc-text-zinc-400">@{user.login}</span>
											) : null}
										</div>
									</div>
								</label>
							</li>
						);
					})}
				</ul>
			)}
		</div>
	);
}

function GuestEmailForm({ entries, onChange }) {
	const [email,   setEmail]   = useState("");
	const [first,   setFirst]   = useState("");
	const [last,    setLast]    = useState("");
	const [error,   setError]   = useState(null);

	const add = (e) => {
		e?.preventDefault();
		setError(null);
		const value = email.trim().toLowerCase();
		if (!value || !value.includes("@")) {
			setError("Enter a valid email address.");
			return;
		}
		if (entries.some((x) => x.email === value)) {
			setError("That email is already in the list.");
			return;
		}
		onChange([
			...entries,
			{
				email:      value,
				first_name: first.trim(),
				last_name:  last.trim(),
			},
		]);
		setEmail(""); setFirst(""); setLast("");
	};

	return (
		<form onSubmit={add} className="zc-space-y-2">
			<p className="zc-text-xs zc-text-zinc-500">
				Add a guest email — the customer doesn't need a registered account.
				Wildcards allowed (<code>*@bigco.com</code>).
			</p>

			<Input
				type="text"
				placeholder="alice@example.com"
				value={email}
				onChange={(e) => setEmail(e.target.value)}
				required
			/>
			<div className="zc-grid zc-grid-cols-2 zc-gap-2">
				<Input
					type="text"
					placeholder="First name (optional)"
					value={first}
					onChange={(e) => setFirst(e.target.value)}
				/>
				<Input
					type="text"
					placeholder="Last name (optional)"
					value={last}
					onChange={(e) => setLast(e.target.value)}
				/>
			</div>

			{error ? (
				<p className="zc-text-xs zc-text-rose-700">{error}</p>
			) : null}

			<Button type="submit" size="sm" variant="outline">+ Add guest</Button>
		</form>
	);
}

/* ============================================================
 * Helpers
 * ============================================================ */

/**
 * Coerce whatever shape the column is in to the new {email, first_name, last_name}
 * object array. Older vouchers store plain strings; both must round-trip.
 */
function normalize(value) {
	if (!Array.isArray(value)) return [];
	return value
		.map((v) => {
			if (typeof v === "string") {
				const email = v.toLowerCase().trim();
				return email ? { email, first_name: "", last_name: "" } : null;
			}
			if (v && typeof v === "object" && typeof v.email === "string") {
				return {
					email:      v.email.toLowerCase().trim(),
					first_name: typeof v.first_name === "string" ? v.first_name : "",
					last_name:  typeof v.last_name  === "string" ? v.last_name  : "",
				};
			}
			return null;
		})
		.filter(Boolean);
}

function displayLabel(entry) {
	const full = [ entry.first_name, entry.last_name ].filter(Boolean).join(" ").trim();
	return full || entry.email;
}

function useDebounced(value, ms) {
	const [v, setV] = useState(value);
	useEffect(() => {
		const t = setTimeout(() => setV(value), ms);
		return () => clearTimeout(t);
	}, [value, ms]);
	return v;
}
