"use client";

import { useEffect, useRef } from "react";

interface RichTextEditorProps {
  id?: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  minHeight?: number;
}

export function RichTextEditor({
  id = "full-description",
  value,
  onChange,
  placeholder = "Write a detailed product description…",
  minHeight = 180,
}: RichTextEditorProps) {
  const editorRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (editorRef.current && editorRef.current.innerHTML !== value) {
      editorRef.current.innerHTML = value;
    }
  }, [value]);

  const exec = (command: string, commandValue?: string) => {
    editorRef.current?.focus();
    document.execCommand(command, false, commandValue);
    onChange(editorRef.current?.innerHTML ?? "");
  };

  return (
    <div className="overflow-hidden rounded-lg border border-zinc-200 bg-white">
      <div className="flex flex-wrap gap-1 border-b border-zinc-200 bg-zinc-50 px-2 py-1.5">
        {[
          { label: "B", command: "bold", title: "Bold" },
          { label: "I", command: "italic", title: "Italic" },
          { label: "U", command: "underline", title: "Underline" },
        ].map((item) => (
          <button
            key={item.command}
            type="button"
            title={item.title}
            onClick={() => exec(item.command)}
            className="flex h-8 min-w-8 items-center justify-center rounded-md border border-zinc-200 bg-white px-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-100"
          >
            {item.label}
          </button>
        ))}
        <button
          type="button"
          title="Bullet list"
          onClick={() => exec("insertUnorderedList")}
          className="rounded-md border border-zinc-200 bg-white px-2.5 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-100"
        >
          • List
        </button>
        <button
          type="button"
          title="Numbered list"
          onClick={() => exec("insertOrderedList")}
          className="rounded-md border border-zinc-200 bg-white px-2.5 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-100"
        >
          1. List
        </button>
        <button
          type="button"
          title="Insert link"
          onClick={() => {
            const url = window.prompt("Enter URL");
            if (url) exec("createLink", url);
          }}
          className="rounded-md border border-zinc-200 bg-white px-2.5 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-100"
        >
          Link
        </button>
      </div>
      <div
        id={id}
        ref={editorRef}
        contentEditable
        suppressContentEditableWarning
        data-placeholder={placeholder}
        onInput={() => onChange(editorRef.current?.innerHTML ?? "")}
        className="admin-input min-h-[180px] rounded-none border-0 px-3 py-3 text-sm leading-relaxed focus:ring-0 empty:before:text-zinc-400 empty:before:content-[attr(data-placeholder)]"
        style={{ minHeight }}
      />
    </div>
  );
}
