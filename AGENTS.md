# AGENTS.md

# GOAL


# CORE RULES
IMPORTANT: Do not invent features or expand scope.  
IMPORTANT: Only edit code when explicitly asked.  
VERY IMPORTANT: Code must follow KISS, DRY, YAGNI, SRP.  
IGNORE: project.htm and anything in .gitignore.  

# STYLE
IMPORTANT: Prefer early returns.  
Rule: One condition per line.  
Rule: Avoid deep nesting.  
Rule: Minimize ternaries.  
NOTE: Keep lines ≤80 cols (soft limit).  

# CODE EDITING RULES
<code_editing_rules>
  <principles>
    - Write code step by step. One condition per line. No combined ifs.  
    - No inline conditional in return. Assign first, then return.  
    - No comprehensions. Use explicit loops with append.  
    - No inline logic. Break steps into small named helpers with clear variables.  
    - No single-letter variables. Always use meaningful names.  
    - Use guard clauses (early returns) instead of deep nesting.  
    - Use the require() helper for skip checks with (condition, logmsg, level).  
    - One responsibility per function. Split filtering, selecting, transforming.  
    - Mirror existing patterns in the codebase.  
    - Make the smallest change that meets the request.  
    - Write clear, simple functions.  
    - Extract constants (paths, strings, numbers) instead of hardcoding.  
    - Keep a terse log of actions.  
    - Use `cp`, `mv`, `sed` for fast edits; avoid rewriting whole files unless required.  
  </principles>

  <persistence>
    - IMPORTANT: Do not ask the human to confirm or clarify assumptions. Decide reasonably and proceed.  
  </persistence>
</code_editing_rules>

